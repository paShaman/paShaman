import test from 'node:test';
import assert from 'node:assert/strict';
import {
  canAddItem,
  nextSortOrder,
  normalizeOrder,
  normalizeWishlistInput,
  partitionItems,
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
