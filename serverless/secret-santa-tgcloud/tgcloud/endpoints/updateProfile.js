import { requireUser, fail } from '../lib/access.js';
import { updateUserProfile } from '../lib/store.js';
import { displayName } from '../lib/utils.js';

export default async function (input, ctx) {
  const auth = await requireUser(ctx);
  const lang = input?.lang == null ? auth.user.lang : String(input.lang);
  if (!['ru'].includes(lang)) fail('VALIDATION', 'Поддерживается только русский язык.');
  const firstName =
    input?.firstName == null ? auth.user.firstName : String(input.firstName).trim().slice(0, 64);
  if (firstName !== null && firstName !== undefined && firstName.length < 1) {
    fail('VALIDATION', 'Имя не может быть пустым.');
  }
  const user = await updateUserProfile(auth.tgId, { lang, firstName });
  return {
    profile: {
      name: displayName(user),
      lang: user.lang,
    },
  };
}
