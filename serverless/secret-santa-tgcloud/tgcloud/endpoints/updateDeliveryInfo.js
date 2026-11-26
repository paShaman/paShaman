import { fail, requireMember } from '../lib/access.js';
import { saveDelivery } from '../lib/store.js';

const METHODS = ['meetup', 'shipping', 'hybrid'];

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  if (['completed', 'cancelled'].includes(auth.campaign.status)) {
    fail('CAMPAIGN_CLOSED', 'Кампания уже завершена.');
  }
  const method =
    auth.campaign.deliveryMode === 'shipping'
      ? 'shipping'
      : METHODS.includes(input?.method)
        ? input.method
        : auth.campaign.deliveryMode;
  if (!METHODS.includes(method)) fail('VALIDATION', 'Выберите способ получения подарка.');

  const recipientName = String(input?.recipientName ?? '').trim().slice(0, 120);
  const address = String(input?.address ?? '').trim().slice(0, 500);
  const phone = String(input?.phone ?? '').trim().slice(0, 40);
  const comment = String(input?.comment ?? '').trim().slice(0, 300);

  const isShipping = method === 'shipping';
  if (isShipping && recipientName.length < 2) {
    fail('VALIDATION', 'Укажите, кому адресован подарок.');
  }
  if (isShipping && address.length < 5) {
    fail('VALIDATION', 'Для доставки нужен адрес.');
  }
  if (isShipping && phone && !/^[+\d()\-\s]{5,40}$/.test(phone)) {
    fail('VALIDATION', 'Телефон содержит недопустимые символы.');
  }

  const delivery = await saveDelivery(auth.campaign.id, auth.tgId, {
    method,
    recipientName: isShipping ? recipientName : '',
    address: isShipping ? address : '',
    phone: isShipping ? phone : '',
    comment: isShipping ? comment : '',
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
