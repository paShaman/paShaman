import { BOT_USERNAME } from './config.js';
import { canConfirm } from './domain/membership.js';
import { displayName } from './utils.js';

export function inviteUrlFor(code) {
  return BOT_USERNAME && code ? `https://t.me/${BOT_USERNAME}?start=join_${code}` : '';
}

export function iso(date) {
  return date ? new Date(date).toISOString() : null;
}

export function serializeMember(member, user, viewerId) {
  return {
    id: member.id,
    name: displayName(user),
    status: member.status,
    role: member.role,
    isMe: Number(member.userId) === Number(viewerId),
    confirmed: member.status === 'confirmed',
  };
}

export function serializeCampaign(campaign, {
  members = [],
  membership = null,
  viewerId,
  wishlistCount = 0,
  delivery = null,
  isOrganizer = false,
} = {}) {
  const visibleMembers = members.filter(
    (member) => !['removed', 'declined'].includes(member.status),
  );
  const progress = {
    total: visibleMembers.length,
    confirmed: visibleMembers.filter((member) => member.status === 'confirmed').length,
  };
  const confirmation = membership
    ? canConfirm({ campaign, member: membership, wishlistCount, delivery })
    : { ok: false, code: 'NOT_MEMBER' };
  return {
    id: campaign.id,
    code: campaign.code,
    inviteUrl: inviteUrlFor(campaign.code),
    title: campaign.title,
    description: campaign.description,
    rules: campaign.rules,
    rulesVersion: campaign.rulesVersion,
    status: campaign.status,
    budget: {
      min: campaign.budgetMin,
      max: campaign.budgetMax,
      currency: campaign.currency,
    },
    deliveryMode: campaign.deliveryMode,
    hasLogo: Boolean(campaign.logoFileId),
    logoVersion: Number(campaign.logoVersion) || 0,
    revealSanta: Boolean(campaign.revealSanta),
    registrationEndsAt: iso(campaign.registrationEndsAt),
    giftDate: iso(campaign.giftDate),
    members: visibleMembers.map((member) => serializeMember(member, member.user, viewerId)),
    progress,
    me: membership
      ? {
          status: membership.status,
          role: membership.role,
          confirmed: membership.status === 'confirmed',
          rulesVersionAccepted: membership.rulesVersionAccepted,
          deliveryMethod: membership.deliveryMethod ?? null,
          wishlistCount,
          deliveryFilled: Boolean(delivery?.address || delivery?.recipientName),
        }
      : null,
    myDelivery: delivery
      ? {
          method: delivery.method,
          recipientName: delivery.recipientName,
          address: delivery.address,
          phone: delivery.phone,
          comment: delivery.comment,
        }
      : null,
    isOrganizer,
    can: {
      acceptRules:
        Boolean(membership) &&
        campaign.status === 'recruiting' &&
        Number(membership.rulesVersionAccepted) !== Number(campaign.rulesVersion),
      confirmParticipation: confirmation.ok === true,
      editWishlist: ['recruiting', 'locked', 'drawn', 'gifting'].includes(campaign.status),
      leave: Boolean(membership) && campaign.status === 'recruiting' && !isOrganizer,
      chat: Boolean(membership) && ['drawn', 'gifting'].includes(campaign.status),
    },
    confirmationHint: confirmation.ok ? null : confirmation.code,
  };
}

export function serializeItem(item, { claimed = false } = {}) {
  return {
    id: item.id,
    title: item.title,
    url: item.url,
    priceMin: item.priceMin,
    priceMax: item.priceMax,
    currency: item.currency,
    comment: item.comment,
    photoVersion: item.photoVersion,
    hasPhoto: Boolean(item.photoFileId),
    claimed: Boolean(claimed),
  };
}

export function serializeThreadMessage(message, viewerId) {
  const system = message.senderRole === 'system';
  return {
    id: message.id,
    system,
    mine: !system && Number(message.senderUserId) === Number(viewerId),
    text: message.text,
    createdAt: iso(message.createdAt),
    readAt: iso(message.readAt),
    reported: Boolean(message.reportedAt),
  };
}
