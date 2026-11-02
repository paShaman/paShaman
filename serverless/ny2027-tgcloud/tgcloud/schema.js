import { boolean, index, integer, json, table, text } from 'sdk/db';

export const users = table('users', {
  chatId: integer('chat_id').primaryKey(),
  telegramId: integer('telegram_id').notNull(),
  username: text('username').notNull().default(''),
  name: text('name').notNull().default(''),
  firstSeen: text('first_seen').notNull(),
  lastSeen: text('last_seen').notNull(),
  blocked: boolean('blocked').notNull().default(false),
  blockedAt: text('blocked_at'),
});

export const registrations = table(
  'registrations',
  {
    id: integer('id').primaryKey({ autoIncrement: true }),
    chatId: integer('chat_id').notNull(),
    fio: text('fio').notNull(),
    company: text('company').notNull(),
    size: text('size').notNull(),
    username: text('username').notNull().default(''),
    tgName: text('tg_name').notNull().default(''),
    status: text('status').notNull().default('active'),
    createdAt: text('created_at').notNull(),
    updatedAt: text('updated_at').notNull(),
    deletedAt: text('deleted_at'),
  },
  (t) => ({
    chatStatusIdx: index('idx_reg_chat_status').on(t.chatId, t.status),
    statusCreatedIdx: index('idx_reg_status_created').on(t.status, t.createdAt),
  }),
);

export const sessions = table('sessions', {
  chatId: integer('chat_id').primaryKey(),
  step: text('step').notNull(),
  data: json('data').notNull(),
  updatedAt: integer('updated_at').notNull(),
});

export const broadcastDrafts = table('broadcast_drafts', {
  adminId: integer('admin_id').primaryKey(),
  step: text('step').notNull(),
  body: text('body').notNull().default(''),
  updatedAt: integer('updated_at').notNull(),
});
