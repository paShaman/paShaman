import { fail, requireMember } from '../lib/access.js';
import { saveDelivery } from '../lib/store.js';

const METHODS = ['meetup', 'shipping', 'hybrid'];

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  if (auth.member.status !== 'confirmed') {
    fail('NOT_CONFIRMED', 'Сначала подтвердите участие.');
  }
  if (['completed', 'cancelled'].includes(auth.campaign.status)) {
    fail('CAMPAIGN_CLOSED', 'Кампания уже завершена.');
  }
  const method = METHODS.includes(input?.method) ? input.method : auth.member.deliveryMethod;
  if (!METHODS.includes(method)) fail('VALIDATION', 'Выберите способ получения подарка.');

  const recipientName = String(input?.recipientName ?? '').trim().slice(0, 120);
  const address = String(input?.address ?? '').trim().slice(0, 500);
  const phone = String(input?.phone ?? '').trim().slice(0, 40);
  const comment = String(input?.comment ?? '').trim().slice(0, 300);

  if (recipientName.length < 2) fail('VALIDATION', 'Укажите, кому адресован подарок.');
  if (method === 'shipping' && address.length < 5) {
    fail('VALIDATION', 'Для доставки нужен адрес.');
  }
  if (phone && !/^[+\d()\-\s]{5,40}$/.test(phone)) {
    fail('VALIDATION', 'Телефон содержит недопустимые символы.');
  }

  const delivery = await saveDelivery(auth.campaign.id, auth.tgId, {
    method,
    recipientName,
    address: method === 'meetup' ? '' : address,
    phone,
    comment,
  });
  return {
    delivery: {
      method: delivery.method,
      recipientName: delivery.recipientName,
      address: delivery.address,
      phone: delivery.phone,
      comment: delivery.comment,
    },
  };
}
