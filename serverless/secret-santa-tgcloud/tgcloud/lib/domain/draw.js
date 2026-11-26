import { MAX_PARTICIPANTS, MIN_PARTICIPANTS } from '../config.js';
import { MatchingError } from './matching-errors.js';

export function makeRng(seed = 1) {
  let state = Number(seed) >>> 0;
  return function rng() {
    state += 0x6d2b79f5;
    let t = state;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function shuffle(list, rng) {
  const result = [...list];
  for (let i = result.length - 1; i > 0; i -= 1) {
    const j = Math.floor(rng() * (i + 1));
    [result[i], result[j]] = [result[j], result[i]];
  }
  return result;
}

function participantId(item) {
  if (item && typeof item === 'object') return Number(item.userId ?? item.id);
  return Number(item);
}

export function normalizeParticipants(participants) {
  if (!Array.isArray(participants)) {
    throw new MatchingError('INVALID_PARTICIPANTS');
  }
  const ids = participants.map(participantId);
  if (ids.some((id) => !Number.isSafeInteger(id) || id <= 0)) {
    throw new MatchingError('INVALID_PARTICIPANTS');
  }
  const unique = [...new Set(ids)];
  if (unique.length !== ids.length) {
    throw new MatchingError('DUPLICATE_PARTICIPANT');
  }
  return unique.sort((a, b) => a - b);
}

export function normalizeExclusions(exclusions) {
  const set = new Set();
  for (const pair of exclusions ?? []) {
    if (!Array.isArray(pair) || pair.length !== 2) continue;
    const a = Number(pair[0]);
    const b = Number(pair[1]);
    if (!Number.isSafeInteger(a) || !Number.isSafeInteger(b) || a <= 0 || b <= 0 || a === b) {
      continue;
    }
    set.add(a < b ? `${a}:${b}` : `${b}:${a}`);
  }
  return set;
}

function isExcluded(excluded, a, b) {
  return excluded.has(a < b ? `${a}:${b}` : `${b}:${a}`);
}

function hopcroftKarp(adjacency) {
  const n = adjacency.length;
  const pairU = new Int32Array(n).fill(-1);
  const pairV = new Int32Array(n).fill(-1);
  const dist = new Int32Array(n);
  let matching = 0;

  function bfs() {
    const queue = [];
    for (let u = 0; u < n; u += 1) {
      if (pairU[u] === -1) {
        dist[u] = 0;
        queue.push(u);
      } else {
        dist[u] = -1;
      }
    }
    let found = false;
    for (let head = 0; head < queue.length; head += 1) {
      const u = queue[head];
      for (const v of adjacency[u]) {
        const next = pairV[v];
        if (next === -1) {
          found = true;
        } else if (dist[next] === -1) {
          dist[next] = dist[u] + 1;
          queue.push(next);
        }
      }
    }
    return found;
  }

  function dfs(u) {
    for (const v of adjacency[u]) {
      const next = pairV[v];
      if (next === -1 || (dist[next] === dist[u] + 1 && dfs(next))) {
        pairU[u] = v;
        pairV[v] = u;
        return true;
      }
    }
    dist[u] = -1;
    return false;
  }

  while (bfs()) {
    for (let u = 0; u < n; u += 1) {
      if (pairU[u] === -1 && dfs(u)) matching += 1;
    }
  }

  return { pairU, pairV, matching };
}

function pairsFromMatching(ids, pairU) {
  return ids.map((giverId, index) => ({
    giverId,
    receiverId: ids[pairU[index]],
  }));
}

export function validateMatching({ participants, exclusions, pairs }) {
  const ids = normalizeParticipants(participants);
  const allowed = new Set(ids);
  const excluded = normalizeExclusions(exclusions);
  if (!Array.isArray(pairs) || pairs.length !== ids.length) {
    throw new MatchingError('INVALID_PAIR');
  }
  const givers = new Set();
  const receivers = new Set();
  for (const pair of pairs) {
    const giver = Number(pair.giverId);
    const receiver = Number(pair.receiverId);
    if (!allowed.has(giver) || !allowed.has(receiver)) {
      throw new MatchingError('INVALID_PAIR', { giver, receiver });
    }
    if (giver === receiver) {
      throw new MatchingError('SELF_PAIR', { giver });
    }
    if (isExcluded(excluded, giver, receiver)) {
      throw new MatchingError('EXCLUDED_PAIR', { giver, receiver });
    }
    if (givers.has(giver)) {
      throw new MatchingError('DUPLICATE_GIVER', { giver });
    }
    if (receivers.has(receiver)) {
      throw new MatchingError('DUPLICATE_RECEIVER', { receiver });
    }
    givers.add(giver);
    receivers.add(receiver);
  }
  return true;
}

export function buildMatching({ participants, exclusions = [], rng = Math.random }) {
  const ids = normalizeParticipants(participants);
  if (ids.length < MIN_PARTICIPANTS) {
    throw new MatchingError('TOO_FEW_PARTICIPANTS', { count: ids.length });
  }
  if (ids.length > MAX_PARTICIPANTS) {
    throw new MatchingError('TOO_MANY_PARTICIPANTS', { count: ids.length });
  }

  const excluded = normalizeExclusions(exclusions);
  const index = new Map(ids.map((id, position) => [id, position]));
  const adjacency = ids.map((giverId) => {
    const receivers = [];
    for (const receiverId of ids) {
      if (receiverId === giverId) continue;
      if (isExcluded(excluded, giverId, receiverId)) continue;
      receivers.push(index.get(receiverId));
    }
    return shuffle(receivers, rng);
  });

  const { pairU, matching } = hopcroftKarp(adjacency);
  if (matching !== ids.length) {
    const usedGivers = new Set();
    const usedReceivers = new Set();
    ids.forEach((id, position) => {
      if (pairU[position] !== -1) {
        usedGivers.add(id);
        usedReceivers.add(ids[pairU[position]]);
      }
    });
    throw new MatchingError('NO_PERFECT_MATCHING', {
      unmatched: ids.filter((id) => !usedGivers.has(id) || !usedReceivers.has(id)),
    });
  }

  const pairs = pairsFromMatching(ids, pairU);
  validateMatching({ participants: ids, exclusions, pairs });
  return pairs;
}

export function swapReceiver({ pairs, giverId, exclusions = [], rng = Math.random }) {
  if (!Array.isArray(pairs)) return null;
  const target = pairs.find((pair) => Number(pair.giverId) === Number(giverId));
  if (!target) return null;

  const others = shuffle(
    pairs.filter((pair) => Number(pair.giverId) !== Number(giverId)),
    rng,
  );

  for (const other of others) {
    const candidate = pairs.map((pair) => {
      if (Number(pair.giverId) === Number(giverId)) {
        return { giverId: Number(pair.giverId), receiverId: Number(other.receiverId) };
      }
      if (Number(pair.giverId) === Number(other.giverId)) {
        return { giverId: Number(pair.giverId), receiverId: Number(target.receiverId) };
      }
      return { giverId: Number(pair.giverId), receiverId: Number(pair.receiverId) };
    });

    try {
      validateMatching({
        participants: pairs.map((pair) => Number(pair.giverId)),
        exclusions,
        pairs: candidate,
      });
      return candidate;
    } catch (error) {
      if (!(error instanceof MatchingError)) throw error;
    }
  }

  return null;
}
