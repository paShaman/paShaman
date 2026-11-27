import test from 'node:test';
import assert from 'node:assert/strict';
import {
  canAddItem,
  nextSortOrder,
  normalizeOrder,
  normalizeWishlistInput,
  partitionItems,
  priceOutOfBudget,
} from '../tgcloud/lib/domain/wishlist.js';
import { WishlistError } from '../tgcloud/lib/domain/wishlist.js';

test('normalizes a wishlist item', () => {
  const item = normalizeWishlistInput({
    title: '  Книга  ',
    url: 'https://example.com/book',
    priceMin: '500',
    priceMax: '1000',
    comment: ' Любая из серии ',
  });
  assert.equal(item.title, 'Книга');
  assert.equal(item.priceMin, 500);
  assert.equal(item.priceMax, 1000);
  assert.equal(item.comment, 'Любая из серии');
});

test('accepts product urls and strips invisible characters', () => {
  const url = 'https://shop.tastycoffee.ru/coffee/advent-game-calendar-2027';
  const item = normalizeWishlistInput({ title: 'Календарь', url: `\u200B${url}\u200B` });
  assert.equal(item.url, url);
});

test('accepts a fixed price within the campaign budget', () => {
  const item = normalizeWishlistInput(
    { title: 'Наушники', priceMin: '2000', priceMax: '2000' },
    { min: 1500, max: 3000 },
  );
  assert.equal(item.priceMin, 2000);
  assert.equal(item.priceMax, 2000);
});

test('rejects a price outside the campaign budget', () => {
  assert.throws(
    () => normalizeWishlistInput({ title: 'x', priceMin: 500, priceMax: 500 }, { min: 1500, max: 3000 }),
    WishlistError,
  );
});

test('accepts an out-of-budget price only with explicit confirmation', () => {
  const item = normalizeWishlistInput(
    { title: 'x', priceMin: 500, priceMax: 500, confirmOutOfBudget: true },
    { min: 1500, max: 3000 },
  );
  assert.equal(item.priceMin, 500);
  assert.throws(
    () =>
      normalizeWishlistInput(
        { title: 'x', priceMin: 500, priceMax: 500, confirmOutOfBudget: 'yes' },
        { min: 1500, max: 3000 },
      ),
    (error) => error instanceof WishlistError && error.code === 'PRICE_OUT_OF_BUDGET',
  );
});

test('detects prices outside the budget', () => {
  assert.deepEqual(priceOutOfBudget(500, 500, { min: 1500, max: 3000 }), {
    min: 1500,
    max: 3000,
  });
  assert.deepEqual(priceOutOfBudget(2000, 5000, { min: 1500, max: 3000 }), {
    min: 1500,
    max: 3000,
  });
  assert.equal(priceOutOfBudget(2000, 2000, { min: 1500, max: 3000 }), null);
  assert.equal(priceOutOfBudget(null, null, { min: 1500, max: 3000 }), null);
});

test('checks the campaign budget when only one bound is set', () => {
  assert.throws(
    () => normalizeWishlistInput({ title: 'x', priceMax: 500000 }, { min: 1500, max: 3000 }),
    (error) => error instanceof WishlistError && error.code === 'PRICE_OUT_OF_BUDGET',
  );
  assert.throws(
    () => normalizeWishlistInput({ title: 'x', priceMin: 100 }, { min: 1500, max: 3000 }),
    WishlistError,
  );
  const item = normalizeWishlistInput(
    { title: 'x', priceMax: 3000 },
    { min: 1500, max: 3000 },
  );
  assert.equal(item.priceMin, null);
  assert.equal(item.priceMax, 3000);
});

test('rejects bad urls, prices and titles', () => {
  assert.throws(() => normalizeWishlistInput({ title: '' }), WishlistError);
  assert.throws(() => normalizeWishlistInput({ title: 'x', url: 'javascript:alert(1)' }), WishlistError);
  assert.throws(() => normalizeWishlistInput({ title: 'x', priceMin: 100, priceMax: 10 }), WishlistError);
  assert.throws(() => normalizeWishlistInput({ title: 'x', priceMin: -1 }), WishlistError);
});

test('computes the next sort order and limit', () => {
  assert.equal(nextSortOrder([]), 1000);
  assert.equal(nextSortOrder([{ sortOrder: 1000 }, { sortOrder: 3000 }]), 4000);
  assert.equal(canAddItem(49), true);
  assert.equal(canAddItem(50), false);
});

test('reorders owned items only', () => {
  const items = [{ id: 1 }, { id: 2 }, { id: 3 }];
  const updates = normalizeOrder(items, [3, 1, 2]);
  assert.deepEqual(updates, [
    { id: 3, sortOrder: 1000 },
    { id: 1, sortOrder: 2000 },
    { id: 2, sortOrder: 3000 },
  ]);
  assert.throws(() => normalizeOrder(items, [1, 2]), WishlistError);
});

test('partitions by sort order', () => {
  const items = [
    { id: 2, sortOrder: 2000 },
    { id: 1, sortOrder: 1000 },
  ];
  assert.deepEqual(partitionItems(items).map((item) => item.id), [1, 2]);
});
