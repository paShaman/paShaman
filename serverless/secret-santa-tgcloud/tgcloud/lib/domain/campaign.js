import {
  MAX_CAMPAIGN_DESCRIPTION,
  MAX_PRICE,
  MAX_RULES,
} from '../config.js';
import { normalizeSpaces } from '../utils.js';

export class CampaignError extends Error {
  constructor(code, details = {}) {
    super(code);
    this.name = 'CampaignError';
    this.code = code;
    this.details = details;
  }
}

export const CAMPAIGN_STATUSES = [
  'recruiting',
  'locked',
  'drawn',
  'gifting',
  'completed',
  'cancelled',
];

const TRANSITIONS = {
  recruiting: ['locked', 'cancelled'],
  locked: ['drawn', 'cancelled'],
  drawn: ['gifting', 'cancelled'],
  gifting: ['completed', 'cancelled'],
  completed: [],
  cancelled: [],
};

export function canTransition(from, to) {
  return (TRANSITIONS[from] ?? []).includes(to);
}

export function assertTransition(from, to) {
  if (!canTransition(from, to)) {
    throw new CampaignError('INVALID_TRANSITION', { from, to });
  }
}

export function isOpenForJoining(campaign) {
  return campaign?.status === 'recruiting';
}

export function isLocked(campaign) {
  return ['locked', 'drawn', 'gifting'].includes(campaign?.status);
}

export function isFinished(campaign) {
  return ['completed', 'cancelled'].includes(campaign?.status);
}

export function canEditWishlist(campaign) {
  return ['recruiting', 'locked', 'drawn', 'gifting'].includes(campaign?.status);
}

export const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

export function makeCode(rng = Math.random, length = 6) {
  let code = '';
  for (let i = 0; i < length; i += 1) {
    code += CODE_ALPHABET[Math.floor(rng() * CODE_ALPHABET.length)];
  }
  return code;
}

export function validateDraft({
  title,
  description,
  budgetMin,
  budgetMax,
  registrationEndsAt,
  giftDate,
  now = new Date(),
}) {
  const cleanTitle = normalizeSpaces(title);
  if (cleanTitle.length < 3 || cleanTitle.length > 80) {
    return { ok: false, code: 'TITLE' };
  }
  const cleanDescription = String(description ?? '').trim();
  if (cleanDescription.length > MAX_CAMPAIGN_DESCRIPTION) {
    return { ok: false, code: 'DESCRIPTION' };
  }
  const min = Number(budgetMin);
  const max = Number(budgetMax);
  if (!Number.isInteger(min) || !Number.isInteger(max) || min < 0 || max > MAX_PRICE) {
    return { ok: false, code: 'BUDGET' };
  }
  if (min > max) return { ok: false, code: 'BUDGET_ORDER' };

  const today = new Date(now);
  today.setHours(0, 0, 0, 0);
  const registration =
    registrationEndsAt instanceof Date ? registrationEndsAt : new Date(registrationEndsAt);
  const gift = giftDate instanceof Date ? giftDate : new Date(giftDate);
  if (Number.isNaN(registration.getTime())) {
    return { ok: false, code: 'REGISTRATION_DATE' };
  }
  if (Number.isNaN(gift.getTime())) {
    return { ok: false, code: 'GIFT_DATE' };
  }
  if (registration.getTime() < today.getTime()) {
    return { ok: false, code: 'REGISTRATION_PAST' };
  }
  if (gift.getTime() < registration.getTime()) {
    return { ok: false, code: 'GIFT_BEFORE_REGISTRATION' };
  }

  return {
    ok: true,
    value: {
      title: cleanTitle,
      description: cleanDescription,
      budgetMin: min,
      budgetMax: max,
      registrationEndsAt: registration,
      giftDate: gift,
    },
  };
}

export function validateRules(value) {
  const text = String(value ?? '').trim();
  if (!text) return { ok: false, code: 'RULES_EMPTY' };
  if (text.length > MAX_RULES) return { ok: false, code: 'RULES_LONG' };
  return { ok: true, value: text };
}
