import {
  boolean,
  index,
  integer,
  json,
  table,
  text,
  uniqueIndex,
} from 'sdk/db';

export const users = table(
  'users',
  {
    tgId: integer('tg_id').primaryKey(),
    username: text('username').notNull().default(''),
    firstName: text('first_name').notNull().default(''),
    lastName: text('last_name').notNull().default(''),
    lang: text('lang').notNull().default('ru'),
    banned: boolean('banned').notNull().default(false),
    blocked: boolean('blocked').notNull().default(false),
    createdAt: integer('created_at', { mode: 'timestamp' }).notNull(),
    updatedAt: integer('updated_at', { mode: 'timestamp' }).notNull(),
  },
  (t) => ({
    bannedIdx: index('idx_users_banned').on(t.banned),
    blockedIdx: index('idx_users_blocked').on(t.blocked),
  }),
);

export const campaigns = table(
  'campaigns',
  {
    id: integer('id').primaryKey({ autoIncrement: true }),
    code: text('code').notNull(),
    title: text('title').notNull(),
    description: text('description').notNull().default(''),
    rules: text('rules').notNull(),
    rulesVersion: integer('rules_version').notNull().default(1),
    budgetMin: integer('budget_min').notNull(),
    budgetMax: integer('budget_max').notNull(),
    currency: text('currency').notNull().default('RUB'),
    status: text('status').notNull().default('recruiting'),
    deliveryMode: text('delivery_mode').notNull().default('hybrid'),
    revealSanta: boolean('reveal_santa').notNull().default(true),
    organizerId: integer('organizer_id').notNull(),
    registrationEndsAt: integer('registration_ends_at', { mode: 'timestamp' }).notNull(),
    giftDate: integer('gift_date', { mode: 'timestamp' }).notNull(),
    lockedAt: integer('locked_at', { mode: 'timestamp' }),
    drawnAt: integer('drawn_at', { mode: 'timestamp' }),
    completedAt: integer('completed_at', { mode: 'timestamp' }),
    cancelledAt: integer('cancelled_at', { mode: 'timestamp' }),
    version: integer('version').notNull().default(1),
    createdAt: integer('created_at', { mode: 'timestamp' }).notNull(),
    updatedAt: integer('updated_at', { mode: 'timestamp' }).notNull(),
  },
  (t) => ({
    codeIdx: uniqueIndex('uidx_campaigns_code').on(t.code),
    organizerStatusIdx: index('idx_campaigns_organizer_status').on(t.organizerId, t.status),
    statusEndsIdx: index('idx_campaigns_status_ends').on(t.status, t.registrationEndsAt),
  }),
);

export const memberships = table(
  'memberships',
  {
    id: integer('id').primaryKey({ autoIncrement: true }),
    campaignId: integer('campaign_id').notNull(),
    userId: integer('user_id').notNull(),
    role: text('role').notNull().default('participant'),
    status: text('status').notNull().default('joined'),
    rulesAcceptedAt: integer('rules_accepted_at', { mode: 'timestamp' }),
    rulesVersionAccepted: integer('rules_version_accepted'),
    confirmedAt: integer('confirmed_at', { mode: 'timestamp' }),
    deliveryMethod: text('delivery_method'),
    createdAt: integer('created_at', { mode: 'timestamp' }).notNull(),
    updatedAt: integer('updated_at', { mode: 'timestamp' }).notNull(),
  },
  (t) => ({
    campaignUserIdx: uniqueIndex('uidx_members_campaign_user').on(t.campaignId, t.userId),
    campaignStatusIdx: index('idx_members_campaign_status').on(t.campaignId, t.status),
    userStatusIdx: index('idx_members_user_status').on(t.userId, t.status),
  }),
);

export const deliveryInfo = table(
  'delivery_info',
  {
    id: integer('id').primaryKey({ autoIncrement: true }),
    campaignId: integer('campaign_id').notNull(),
    userId: integer('user_id').notNull(),
    method: text('method').notNull().default('meetup'),
    recipientName: text('recipient_name').notNull().default(''),
    address: text('address').notNull().default(''),
    phone: text('phone').notNull().default(''),
    comment: text('comment').notNull().default(''),
    updatedAt: integer('updated_at', { mode: 'timestamp' }).notNull(),
  },
  (t) => ({
    campaignUserIdx: uniqueIndex('uidx_delivery_campaign_user').on(t.campaignId, t.userId),
  }),
);

export const exclusions = table(
  'exclusions',
  {
    id: integer('id').primaryKey({ autoIncrement: true }),
    campaignId: integer('campaign_id').notNull(),
    userA: integer('user_a').notNull(),
    userB: integer('user_b').notNull(),
    createdAt: integer('created_at', { mode: 'timestamp' }).notNull(),
  },
  (t) => ({
    campaignPairIdx: uniqueIndex('uidx_exclusions_campaign_pair').on(t.campaignId, t.userA, t.userB),
    campaignIdx: index('idx_exclusions_campaign').on(t.campaignId),
  }),
);

export const wishlistItems = table(
  'wishlist_items',
  {
    id: integer('id').primaryKey({ autoIncrement: true }),
    campaignId: integer('campaign_id').notNull(),
    userId: integer('user_id').notNull(),
    title: text('title').notNull(),
    url: text('url').notNull().default(''),
    priceMin: integer('price_min'),
    priceMax: integer('price_max'),
    currency: text('currency').notNull().default('RUB'),
    comment: text('comment').notNull().default(''),
    photoFileId: text('photo_file_id'),
    photoVersion: integer('photo_version').notNull().default(0),
    sortOrder: integer('sort_order').notNull().default(0),
    createdAt: integer('created_at', { mode: 'timestamp' }).notNull(),
    updatedAt: integer('updated_at', { mode: 'timestamp' }).notNull(),
  },
  (t) => ({
    campaignUserOrderIdx: index('idx_wishlist_campaign_user_order').on(
      t.campaignId,
      t.userId,
      t.sortOrder,
    ),
  }),
);

export const claims = table(
  'claims',
  {
    itemId: integer('item_id').primaryKey(),
    campaignId: integer('campaign_id').notNull(),
    santaId: integer('santa_id').notNull(),
    assignmentId: integer('assignment_id').notNull(),
    claimedAt: integer('claimed_at', { mode: 'timestamp' }).notNull(),
  },
  (t) => ({
    campaignSantaIdx: index('idx_claims_campaign_santa').on(t.campaignId, t.santaId),
  }),
);

export const assignments = table(
  'assignments',
  {
    id: integer('id').primaryKey({ autoIncrement: true }),
    campaignId: integer('campaign_id').notNull(),
    giverId: integer('giver_id').notNull(),
    receiverId: integer('receiver_id').notNull(),
    status: text('status').notNull().default('draft'),
    giftStatus: text('gift_status').notNull().default('pending'),
    sentAt: integer('sent_at', { mode: 'timestamp' }),
    receivedAt: integer('received_at', { mode: 'timestamp' }),
    revealedAt: integer('revealed_at', { mode: 'timestamp' }),
    chatBlocked: boolean('chat_blocked').notNull().default(false),
    chatBlockedAt: integer('chat_blocked_at', { mode: 'timestamp' }),
    version: integer('version').notNull().default(1),
    createdAt: integer('created_at', { mode: 'timestamp' }).notNull(),
    updatedAt: integer('updated_at', { mode: 'timestamp' }).notNull(),
  },
  (t) => ({
    campaignGiverIdx: uniqueIndex('uidx_assignments_campaign_giver').on(t.campaignId, t.giverId),
    campaignReceiverIdx: uniqueIndex('uidx_assignments_campaign_receiver').on(
      t.campaignId,
      t.receiverId,
    ),
    campaignStatusIdx: index('idx_assignments_campaign_status').on(t.campaignId, t.status),
  }),
);

export const chatMessages = table(
  'chat_messages',
  {
    id: integer('id').primaryKey({ autoIncrement: true }),
    campaignId: integer('campaign_id').notNull(),
    assignmentId: integer('assignment_id').notNull(),
    senderRole: text('sender_role').notNull(),
    senderUserId: integer('sender_user_id').notNull(),
    clientMsgId: text('client_msg_id').notNull(),
    text: text('text').notNull(),
    createdAt: integer('created_at', { mode: 'timestamp' }).notNull(),
    readAt: integer('read_at', { mode: 'timestamp' }),
    reportedAt: integer('reported_at', { mode: 'timestamp' }),
    reportedBy: integer('reported_by'),
    reportReason: text('report_reason').notNull().default(''),
    hiddenAt: integer('hidden_at', { mode: 'timestamp' }),
    hiddenBy: integer('hidden_by'),
  },
  (t) => ({
    assignmentCreatedIdx: index('idx_chat_assignment_created').on(t.assignmentId, t.createdAt),
    assignmentClientIdx: uniqueIndex('uidx_chat_assignment_client').on(
      t.assignmentId,
      t.clientMsgId,
    ),
    campaignReportedIdx: index('idx_chat_campaign_reported').on(t.campaignId, t.reportedAt),
  }),
);

export const sessions = table('sessions', {
  userId: integer('user_id').primaryKey(),
  step: text('step').notNull(),
  data: json('data').notNull(),
  updatedAt: integer('updated_at').notNull(),
});

export const rateLimits = table('rate_limits', {
  key: text('key').primaryKey(),
  windowStart: integer('window_start').notNull(),
  count: integer('count').notNull().default(0),
});

export const broadcasts = table(
  'broadcasts',
  {
    id: integer('id').primaryKey({ autoIncrement: true }),
    campaignId: integer('campaign_id').notNull(),
    authorId: integer('author_id').notNull(),
    kind: text('kind').notNull(),
    text: text('text').notNull(),
    recipients: integer('recipients').notNull().default(0),
    sent: integer('sent').notNull().default(0),
    blocked: integer('blocked').notNull().default(0),
    failed: integer('failed').notNull().default(0),
    createdAt: integer('created_at', { mode: 'timestamp' }).notNull(),
  },
  (t) => ({
    campaignIdx: index('idx_broadcasts_campaign').on(t.campaignId, t.createdAt),
  }),
);

export const auditLog = table(
  'audit_log',
  {
    id: integer('id').primaryKey({ autoIncrement: true }),
    campaignId: integer('campaign_id'),
    actorId: integer('actor_id').notNull(),
    action: text('action').notNull(),
    payload: json('payload').notNull(),
    createdAt: integer('created_at', { mode: 'timestamp' }).notNull(),
  },
  (t) => ({
    campaignIdx: index('idx_audit_campaign').on(t.campaignId, t.createdAt),
    actionIdx: index('idx_audit_action').on(t.action, t.createdAt),
  }),
);
