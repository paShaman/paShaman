import { boolean, index, integer, json, table, text } from 'sdk/db';

export const users = table('users', {
  id: integer('id').primaryKey(),
  name: text('name').notNull(),
  username: text('username').notNull().default(''),
  role: text('role').notNull().default('user'),
  annual_days: integer('annual_days'),
  active: boolean('active').notNull().default(true),
  created_at: text('created_at').notNull(),
});

export const records = table('records', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  user_id: integer('user_id').notNull(),
  type: text('type').notNull(),
  date_from: text('date_from').notNull(),
  date_to: text('date_to').notNull(),
  days: integer('days').notNull(),
  comment: text('comment').notNull().default(''),
  finished_at: text('finished_at'),
  created_at: text('created_at').notNull(),
  updated_at: text('updated_at').notNull(),
}, (t) => ({
  userIdx: index('idx_rec_user').on(t.user_id),
  dateIdx: index('idx_rec_date').on(t.date_from, t.date_to),
}));

export const sessions = table('sessions', {
  user_id: integer('user_id').primaryKey(),
  step: text('step').notNull(),
  data: json('data').notNull(),
  ts: integer('ts').notNull(),
});
