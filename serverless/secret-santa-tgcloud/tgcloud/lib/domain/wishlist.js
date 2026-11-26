import {
  MAX_ITEM_COMMENT,
  MAX_ITEM_TITLE,
  MAX_PRICE,
  MAX_WISHLIST_ITEMS,
} from '../config.js';
import { normalizeSpaces, validUrl } from '../utils.js';

export class WishlistError extends Error {
  constructor(code, details = {}) {
    super(code);
    this.name = 'WishlistError';
    this.code = code;
    this.details = details;
  }
}

export function normalizeWishlistInput(input = {}, budget = null) {
  const title = normalizeSpaces(input.title);
  if (title.length < 1) throw new WishlistError('TITLE_EMPTY');
  if (title.length > MAX_ITEM_TITLE) throw new WishlistError('TITLE_LONG');

  const url = String(input.url ?? '').trim();
  if (url && !validUrl(url)) throw new WishlistError('URL_INVALID');

  const priceMin = input.priceMin == null || input.priceMin === '' ? null : Number(input.priceMin);
  const priceMax = input.priceMax == null || input.priceMax === '' ? null : Number(input.priceMax);
  for (const [name, value] of [
    ['priceMin', priceMin],
    ['priceMax', priceMax],
  ]) {
    if (value != null && (!Number.isInteger(value) || value < 0 || value > MAX_PRICE)) {
      throw new WishlistError('PRICE_INVALID', { field: name });
    }
  }
  if (priceMin != null && priceMax != null && priceMin > priceMax) {
    throw new WishlistError('PRICE_ORDER');
  }
  if (budget && budget.min != null && budget.max != null && priceMin != null) {
    const min = Number(budget.min);
    const max = Number(budget.max);
    if (Number.isFinite(min) && Number.isFinite(max)) {
      const high = priceMax ?? priceMin;
      if (priceMin < min || high > max) {
        throw new WishlistError('PRICE_OUT_OF_BUDGET', { field: 'price', min, max });
      }
    }
  }

  const comment = String(input.comment ?? '').trim();
  if (comment.length > MAX_ITEM_COMMENT) throw new WishlistError('COMMENT_LONG');

  const currency = String(input.currency ?? 'RUB').trim().toUpperCase().slice(0, 3) || 'RUB';

  return {
    title,
    url,
    priceMin,
    priceMax,
    currency,
    comment,
  };
}

export function nextSortOrder(items) {
  const max = (items ?? []).reduce((value, item) => Math.max(value, Number(item.sortOrder) || 0), 0);
  return max + 1000;
}

export function canAddItem(count) {
  return Number(count) < MAX_WISHLIST_ITEMS;
}

export function normalizeOrder(items, itemIds) {
  const ids = Array.isArray(itemIds) ? itemIds.map(Number) : [];
  const owned = new Set((items ?? []).map((item) => Number(item.id)));
  if (ids.length !== owned.size) throw new WishlistError('ORDER_INVALID');
  if (new Set(ids).size !== ids.length) throw new WishlistError('ORDER_INVALID');
  if (ids.some((id) => !owned.has(id))) throw new WishlistError('ORDER_INVALID');
  return ids.map((id, index) => ({ id, sortOrder: (index + 1) * 1000 }));
}

export function partitionItems(items) {
  return [...(items ?? [])].sort((a, b) => {
    const order = (Number(a.sortOrder) || 0) - (Number(b.sortOrder) || 0);
    return order !== 0 ? order : Number(a.id) - Number(b.id);
  });
}
