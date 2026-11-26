export const ACTIVE_STATUSES = ['joined', 'rules_accepted', 'confirmed'];

export function isActiveMember(member) {
  return Boolean(member) && ACTIVE_STATUSES.includes(member.status);
}

export function isConfirmed(member) {
  return member?.status === 'confirmed';
}

export function canAcceptRules(campaign, member) {
  if (!member) return { ok: false, code: 'NOT_MEMBER' };
  if (campaign.status !== 'recruiting') return { ok: false, code: 'CAMPAIGN_LOCKED' };
  return { ok: true };
}

export function canConfirm({ campaign, member, wishlistCount, delivery }) {
  if (!member) return { ok: false, code: 'NOT_MEMBER' };
  if (campaign.status !== 'recruiting') return { ok: false, code: 'CAMPAIGN_LOCKED' };
  if (member.status === 'removed' || member.status === 'declined') {
    return { ok: false, code: 'NOT_MEMBER' };
  }
  if (member.status === 'confirmed') return { ok: true, already: true };
  if (Number(member.rulesVersionAccepted) !== Number(campaign.rulesVersion)) {
    return { ok: false, code: 'RULES_NOT_ACCEPTED' };
  }
  if (Number(wishlistCount) < 1) {
    return { ok: false, code: 'WISHLIST_EMPTY' };
  }
  const needsAddress =
    campaign.deliveryMode === 'shipping' ||
    (campaign.deliveryMode === 'hybrid' && delivery?.method === 'shipping');
  if (needsAddress) {
    const recipientName = String(delivery?.recipientName ?? '').trim();
    const address = String(delivery?.address ?? '').trim();
    if (recipientName.length < 2 || address.length < 5) {
      return { ok: false, code: 'DELIVERY_REQUIRED' };
    }
  } else if (campaign.deliveryMode === 'hybrid') {
    const method = delivery?.method;
    if (method !== 'meetup' && method !== 'shipping') {
      return { ok: false, code: 'DELIVERY_METHOD_REQUIRED' };
    }
  }
  return { ok: true };
}

export function progress(members) {
  const total = members.filter((member) => isActiveMember(member)).length;
  const confirmed = members.filter((member) => member.status === 'confirmed').length;
  const accepted = members.filter((member) =>
    ['rules_accepted', 'confirmed'].includes(member.status),
  ).length;
  return { total, confirmed, accepted };
}

export function drawParticipants(members) {
  return members
    .filter((member) => member.status === 'confirmed')
    .map((member) => member.userId);
}

export function canDraw({ members, campaign }) {
  if (campaign.status !== 'locked') return { ok: false, code: 'CAMPAIGN_NOT_LOCKED' };
  const participants = drawParticipants(members);
  if (participants.length < 3) return { ok: false, code: 'TOO_FEW_PARTICIPANTS' };
  return { ok: true, participants };
}
