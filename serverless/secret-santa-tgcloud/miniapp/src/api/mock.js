const demoUser = { id: 900001, name: 'Аня' };

const state = {
  campaign: {
    id: 1,
    code: 'XMAS26',
    inviteUrl: 'https://t.me/pod_elochku_bot?start=join_XMAS26',
    title: 'Тайный Санта 2027',
    description: 'Обмениваемся подарками в офисе и не раскрываем себя до вручения.',
    rules:
      '1. Бюджет — 1500–3000 ₽.\n2. Не раскрываем Санту до завершения.\n3. Вишлист — подсказка, а не заказ.\n4. Уважаем друг друга.',
    rulesVersion: 1,
    status: 'recruiting',
    budget: { min: 1500, max: 3000, currency: 'RUB' },
    deliveryMode: 'hybrid',
    hasLogo: false,
    logoVersion: 0,
    revealSanta: true,
    registrationEndsAt: new Date(Date.now() + 12 * 86400000).toISOString(),
    giftDate: new Date(Date.now() + 30 * 86400000).toISOString(),
  },
  me: {
    status: 'joined',
    rulesAccepted: false,
    confirmed: false,
    deliveryMethod: null,
    delivery: null,
  },
  members: [
    { id: 1, userId: 900001, name: 'Аня', status: 'joined', role: 'organizer', isMe: true },
    { id: 2, userId: 900002, name: 'Борис', status: 'confirmed', role: 'participant', isMe: false },
    { id: 3, userId: 900003, name: 'Вера', status: 'confirmed', role: 'participant', isMe: false },
    { id: 4, userId: 900004, name: 'Гриша', status: 'rules_accepted', role: 'participant', isMe: false },
  ],
  wishlist: [
    {
      id: 1,
      title: 'Тёплый плед',
      url: 'https://example.com/plaid',
      priceMin: 2000,
      priceMax: 2000,
      currency: 'RUB',
      comment: 'Любой спокойный цвет',
      hasPhoto: false,
      photoVersion: 0,
    },
    {
      id: 2,
      title: 'Книга про космос',
      url: '',
      priceMin: 1600,
      priceMax: 1600,
      currency: 'RUB',
      comment: '',
      hasPhoto: false,
      photoVersion: 0,
    },
  ],
  assignment: {
    giver: { name: 'Виктор' },
    receiver: { name: 'Борис' },
    delivery: {
      method: 'shipping',
      recipientName: 'Борис',
      address: 'Санкт-Петербург, ул. Примерная, 7',
      phone: '+7 900 000-00-00',
      comment: 'Код домофона 1234',
    },
    giftStatus: 'pending',
    chatBlocked: false,
  },
  messages: [
    {
      id: 1,
      mine: false,
      text: 'Привет! Я твой Санта. Уже присмотрел(а) плед, но уточню: размер большой?',
      createdAt: new Date(Date.now() - 3600000).toISOString(),
      readAt: null,
    },
    {
      id: 2,
      mine: true,
      text: 'Привет! Да, большой и мягкий. Спасибо!',
      createdAt: new Date(Date.now() - 3200000).toISOString(),
      readAt: new Date().toISOString(),
    },
  ],
};

let nextWishlistId = 3;

function fail(code, message) {
  const error = new Error(message);
  error.code = code;
  error.parameters = { code };
  throw error;
}

function isOutOfBudget(item) {
  const { min, max } = state.campaign.budget;
  const low = item.priceMin ?? item.priceMax;
  const high = item.priceMax ?? item.priceMin;
  if (low == null && high == null) return false;
  return low < min || high > max;
}

function campaignPayload() {
  const confirmed = state.members.filter((member) => member.status === 'confirmed').length;
  const total = state.members.length;
  const accepted = state.me.rulesAccepted || state.me.confirmed;
  return {
    ...state.campaign,
    members: state.members.map((member) => ({
      ...member,
      confirmed: member.status === 'confirmed',
    })),
    progress: { total, confirmed },
    me: {
      status: state.me.status,
      role: 'organizer',
      confirmed: state.me.confirmed,
      rulesVersionAccepted: accepted ? state.campaign.rulesVersion : null,
      deliveryMethod: state.me.deliveryMethod,
      wishlistCount: state.wishlist.length,
      deliveryFilled: Boolean(state.me.delivery),
    },
    isOrganizer: true,
    myDelivery: state.me.delivery
      ? {
          method: state.me.delivery.method,
          recipientName: state.me.delivery.recipientName,
          address: state.me.delivery.address,
          phone: state.me.delivery.phone,
          comment: state.me.delivery.comment,
        }
      : null,
    can: {
      acceptRules: !accepted,
      confirmParticipation: accepted && state.wishlist.length > 0,
      editWishlist: true,
      leave: false,
      chat: false,
    },
    confirmationHint: accepted && state.wishlist.length > 0 ? null : 'WISHLIST_EMPTY',
  };
}

function assignmentPayload() {
  return {
    campaign: {
      id: state.campaign.id,
      title: state.campaign.title,
      status: state.campaign.status,
      revealSanta: true,
    },
    receiver: { name: state.assignment.receiver.name },
    wishlist: state.wishlist.map((item) => ({ ...item, claimed: false, outOfBudget: isOutOfBudget(item) })),
    delivery: state.assignment.delivery ? { ...state.assignment.delivery } : null,
    giftStatus: state.assignment.giftStatus,
    chatBlocked: state.assignment.chatBlocked,
    chatAvailable: true,
  };
}

function bootstrapPayload() {
  return {
    user: { name: demoUser.name, photoUrl: '', lang: 'ru', isSuperAdmin: false },
    startParam: null,
    appUrl: '',
    photoMaxBytes: 320 * 1024,
    limits: { participants: 500, wishlistItems: 50, broadcast: 100, chatText: 1000 },
    campaigns: [
      {
        id: state.campaign.id,
        code: state.campaign.code,
        title: state.campaign.title,
        status: state.campaign.status,
        role: 'organizer',
        membershipStatus: state.me.status,
        registrationEndsAt: state.campaign.registrationEndsAt,
        giftDate: state.campaign.giftDate,
        hasLogo: state.campaign.hasLogo,
        logoVersion: state.campaign.logoVersion,
        budget: state.campaign.budget,
        confirmed: state.me.confirmed,
      },
    ],
  };
}

export async function mockCall(name, input = {}) {
  await new Promise((resolve) => setTimeout(resolve, 140));

  if (name === 'bootstrap') return bootstrapPayload();
  if (name === 'getCampaign') return campaignPayload();
  if (name === 'getMemberAvatar') return { photo: '' };
  if (name === 'getMyAvatar') return { photo: '' };
  if (name === 'getCampaignLogo') return { photo: '', version: 0 };
  if (name === 'joinCampaign') {
    if (String(input.code || '').toUpperCase() !== state.campaign.code) {
      fail('NOT_FOUND', 'Кампания с таким кодом не найдена.');
    }
    return { campaignId: state.campaign.id, status: state.me.status };
  }
  if (name === 'acceptRules') {
    state.me.rulesAccepted = true;
    state.me.status = 'rules_accepted';
    return { status: 'rules_accepted' };
  }
  if (name === 'confirmParticipation') {
    if (!state.wishlist.length) fail('WISHLIST_EMPTY', 'Добавьте хотя бы одно желание.');
    state.me.confirmed = true;
    state.me.status = 'confirmed';
    const me = state.members.find((member) => member.isMe);
    if (me) me.status = 'confirmed';
    return { status: 'confirmed' };
  }
  if (name === 'leaveCampaign') {
    state.me.status = 'declined';
    return { ok: true };
  }
  if (name === 'updateDeliveryInfo') {
    state.me.delivery = { ...input };
    state.me.deliveryMethod = input.method;
    return { delivery: { ...input } };
  }
  if (name === 'getMyWishlist') {
    return {
      items: state.wishlist.map((item) => ({ ...item, outOfBudget: isOutOfBudget(item) })),
      canEdit: true,
      limit: 50,
    };
  }
  if (name === 'saveWishlistItem') {
    const budget = state.campaign.budget;
    const priceMin = input.priceMin ?? null;
    const priceMax = input.priceMax ?? null;
    const outside =
      (priceMin != null || priceMax != null) &&
      ((priceMin ?? priceMax) < budget.min || (priceMax ?? priceMin) > budget.max);
    if (outside && input.confirmOutOfBudget !== true) {
      fail('PRICE_OUT_OF_BUDGET', 'Цена вне бюджета кампании. Подтвердите добавление.');
    }
    if (input.itemId) {
      const item = state.wishlist.find((row) => row.id === Number(input.itemId));
      if (!item) fail('NOT_FOUND', 'Желание не найдено.');
      Object.assign(item, {
        title: input.title,
        url: input.url ?? '',
        priceMin,
        priceMax,
        currency: 'RUB',
        comment: input.comment ?? '',
      });
      return { item: { ...item, outOfBudget: isOutOfBudget(item) } };
    }
    const item = {
      id: nextWishlistId++,
      title: input.title,
      url: input.url ?? '',
      priceMin,
      priceMax,
      currency: 'RUB',
      comment: input.comment ?? '',
      hasPhoto: false,
      photoVersion: 0,
    };
    state.wishlist.push(item);
    return { item: { ...item, outOfBudget: isOutOfBudget(item) } };
  }
  if (name === 'deleteWishlistItem') {
    state.wishlist = state.wishlist.filter((item) => item.id !== Number(input.itemId));
    return { ok: true };
  }
  if (name === 'reorderWishlist') {
    const order = (input.itemIds || []).map(Number);
    state.wishlist.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
    return { ok: true };
  }
  if (name === 'uploadItemPhoto') {
    const item = state.wishlist.find((row) => row.id === Number(input.itemId));
    if (!item) fail('NOT_FOUND', 'Желание не найдено.');
    item.hasPhoto = true;
    item.photoVersion += 1;
    return { version: item.photoVersion };
  }
  if (name === 'deleteItemPhoto') {
    const item = state.wishlist.find((row) => row.id === Number(input.itemId));
    if (item) {
      item.hasPhoto = false;
      item.photoVersion += 1;
    }
    return { ok: true };
  }
  if (name === 'getItemPhoto') {
    return {
      version: 1,
      dataUrl:
        'data:image/svg+xml;utf8,' +
        encodeURIComponent(
          '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="220"><rect width="320" height="220" fill="#f6eee2"/><circle cx="160" cy="110" r="64" fill="#dbc3c3"/><path d="M80 160h160" stroke="#536f6b" stroke-width="10" stroke-linecap="round"/></svg>',
        ),
    };
  }
  if (name === 'getAssignment') return assignmentPayload();
  if (name === 'getGiftStatus') {
    const reveal = state.campaign.status === 'completed';
    const side = (role, partnerName) => ({
      role,
      giftStatus: state.assignment.giftStatus,
      chatBlocked: false,
      deliveryMethod: state.assignment.delivery?.method ?? null,
      partnerName,
      revealAvailable: reveal,
    });
    const santa = side('santa', state.assignment.receiver.name);
    const recipient = side('recipient', reveal ? state.assignment.giver.name : null);
    return {
      role: 'santa',
      giftStatus: state.assignment.giftStatus,
      chatBlocked: false,
      campaignStatus: state.campaign.status,
      campaign: {
        id: state.campaign.id,
        title: state.campaign.title,
        status: state.campaign.status,
        revealSanta: true,
      },
      partnerName: santa.partnerName,
      revealAvailable: reveal,
      roles: { santa: true, recipient: true },
      santa,
      recipient,
    };
  }
  if (name === 'setItemClaimed') return { claimed: Boolean(input.claimed) };
  if (name === 'getUnread') {
    const unread = state.messages.filter((message) => !message.mine && !message.readAt).length;
    return { roles: ['santa', 'recipient'], unread: { santa: 0, recipient: unread } };
  }
  if (name === 'getThread') {
    if (input.markRead) {
      state.messages.forEach((message) => {
        if (!message.mine) message.readAt = new Date().toISOString();
      });
    }
    return {
      role: input.role === 'recipient' ? 'recipient' : 'santa',
      roles: ['santa', 'recipient'],
      chatBlocked: state.assignment.chatBlocked,
      campaignStatus: state.campaign.status,
      messages: state.messages.map((message) => ({ ...message })),
    };
  }
  if (name === 'sendChatMessage') {
    const message = {
      id: state.messages.length + 1,
      mine: true,
      text: input.text,
      createdAt: new Date().toISOString(),
      readAt: null,
    };
    state.messages.push(message);
    return { message: { ...message } };
  }
  if (name === 'reportMessage') return { ok: true };
  if (name === 'markThreadRead') return { ok: true };
  if (name === 'markGiftStatus') {
    if (state.assignment.delivery?.method !== 'shipping') {
      fail('STATUS_NOT_REQUIRED', 'Для личной передачи статусы не нужны.');
    }
    if (state.assignment.giftStatus !== input.status) {
      state.assignment.giftStatus = input.status;
      state.messages.push({
        id: state.messages.length + 1,
        system: true,
        mine: false,
        text: input.status === 'sent' ? '🎁 Подарок отправлен' : '✅ Подарок получен',
        createdAt: new Date().toISOString(),
        readAt: null,
      });
    }
    return { status: input.status };
  }
  if (name === 'updateProfile') return { profile: { name: input.firstName || demoUser.name, lang: 'ru' } };

  fail('NOT_FOUND', `Нет mock-обработчика для ${name}.`);
}
