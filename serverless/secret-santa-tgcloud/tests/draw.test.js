import test from 'node:test';
import assert from 'node:assert/strict';
import {
  buildMatching,
  makeRng,
  normalizeExclusions,
  normalizeParticipants,
  swapReceiver,
  validateMatching,
} from '../tgcloud/lib/domain/draw.js';
import { MatchingError } from '../tgcloud/lib/domain/matching-errors.js';

function ids(count) {
  return Array.from({ length: count }, (_, index) => 100 + index);
}

test('builds a valid matching for ten participants', () => {
  const participants = ids(10);
  const pairs = buildMatching({ participants, rng: makeRng(42) });
  assert.equal(pairs.length, 10);
  assert.equal(validateMatching({ participants, exclusions: [], pairs }), true);
  const givers = new Set(pairs.map((pair) => pair.giverId));
  const receivers = new Set(pairs.map((pair) => pair.receiverId));
  assert.equal(givers.size, 10);
  assert.equal(receivers.size, 10);
});

test('rejects fewer than three participants', () => {
  assert.throws(
    () => buildMatching({ participants: ids(2), rng: makeRng(1) }),
    (error) => error instanceof MatchingError && error.code === 'TOO_FEW_PARTICIPANTS',
  );
});

test('rejects an impossible exclusion set', () => {
  assert.throws(
    () =>
      buildMatching({
        participants: [1, 2, 3],
        exclusions: [[1, 2]],
        rng: makeRng(1),
      }),
    (error) => error instanceof MatchingError && error.code === 'NO_PERFECT_MATCHING',
  );
});

test('respects exclusions for fifty participants', () => {
  const participants = ids(50);
  const exclusions = [
    [participants[0], participants[1]],
    [participants[2], participants[3]],
    [participants[4], participants[5]],
  ];
  const pairs = buildMatching({ participants, exclusions, rng: makeRng(7) });
  assert.equal(validateMatching({ participants, exclusions, pairs }), true);
  for (const [a, b] of exclusions) {
    assert.ok(
      !pairs.some(
        (pair) =>
          (pair.giverId === a && pair.receiverId === b) ||
          (pair.giverId === b && pair.receiverId === a),
      ),
    );
  }
});

test('is deterministic for the same seed and different for another', () => {
  const participants = ids(20);
  const first = buildMatching({ participants, rng: makeRng(99) });
  const second = buildMatching({ participants, rng: makeRng(99) });
  const third = buildMatching({ participants, rng: makeRng(100) });
  assert.deepEqual(first, second);
  assert.notDeepEqual(first, third);
});

test('swaps one receiver and changes exactly two pairs', () => {
  const participants = ids(6);
  const pairs = buildMatching({ participants, rng: makeRng(3) });
  const target = pairs[0];
  const swapped = swapReceiver({ pairs, giverId: target.giverId, rng: makeRng(4) });
  assert.ok(swapped);
  assert.equal(validateMatching({ participants, exclusions: [], pairs: swapped }), true);
  const changed = swapped.filter((pair, index) => {
    const before = pairs.find((item) => item.giverId === pair.giverId);
    return before.receiverId !== pair.receiverId;
  });
  assert.equal(changed.length, 2);
  assert.notEqual(
    swapped.find((pair) => pair.giverId === target.giverId).receiverId,
    target.receiverId,
  );
});

test('normalizes exclusion pairs and participants', () => {
  assert.deepEqual(normalizeParticipants([3, 1, 2]), [1, 2, 3]);
  assert.throws(() => normalizeParticipants([3, 3]), MatchingError);
  const set = normalizeExclusions([[5, 2], [2, 5], [4, 4]]);
  assert.equal(set.size, 1);
  assert.ok(set.has('2:5'));
});
