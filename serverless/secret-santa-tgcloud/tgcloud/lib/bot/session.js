import { clearSession, getSession, setSession } from '../store.js';

export async function getState(userId) {
  return getSession(userId);
}

export async function setStep(userId, step, data = {}) {
  return setSession(userId, { step, data });
}

export async function clear(userId) {
  await clearSession(userId);
}

export async function startWizard(userId) {
  await setStep(userId, 'wizard:title', { draft: { deliveryMode: 'hybrid', revealSanta: true } });
}

export async function patchWizard(userId, patch) {
  const session = await getSession(userId);
  if (!session || !String(session.step).startsWith('wizard:')) return null;
  const data = { ...(session.data ?? {}), draft: { ...(session.data?.draft ?? {}), ...patch } };
  await setStep(userId, session.step, data);
  return { ...session, data };
}

export async function setWizardStep(userId, step, patch = {}) {
  const session = await getSession(userId);
  const data = { ...(session?.data ?? {}), draft: { ...(session?.data?.draft ?? {}), ...patch } };
  await setStep(userId, step, data);
  return { ...session, step, data };
}

export async function startBroadcast(userId, campaignId) {
  await setStep(userId, 'broadcast:text', { campaignId });
}

export async function previewBroadcast(userId, campaignId, text, kind) {
  await setStep(userId, 'broadcast:preview', { campaignId, text, kind });
}
