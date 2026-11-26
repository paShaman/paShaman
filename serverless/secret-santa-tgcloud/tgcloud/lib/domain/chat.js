import { MAX_CHAT_TEXT } from '../config.js';

export const CHAT_LIMIT_MINUTE = 10;
export const CHAT_LIMIT_HOUR = 60;

export function normalizeChatText(value) {
  const text = String(value ?? '').replace(/\r\n/g, '\n').trim();
  if (!text) return { ok: false, code: 'EMPTY' };
  if (text.length > MAX_CHAT_TEXT) return { ok: false, code: 'LONG' };
  return { ok: true, text };
}

export function checkChatRate(minuteCount, hourCount) {
  if (Number(hourCount) >= CHAT_LIMIT_HOUR) {
    return { ok: false, retryAfter: 3600 };
  }
  if (Number(minuteCount) >= CHAT_LIMIT_MINUTE) {
    return { ok: false, retryAfter: 60 };
  }
  return { ok: true, retryAfter: 0 };
}

export function roleForAssignment(assignment, userId) {
  if (!assignment) return null;
  if (Number(assignment.giverId) === Number(userId)) return 'santa';
  if (Number(assignment.receiverId) === Number(userId)) return 'recipient';
  return null;
}

export function isMessageVisible(message) {
  return !message?.hiddenAt;
}

export function visibleMessages(messages) {
  return (messages ?? []).filter(isMessageVisible);
}

export function canReportMessage(message, userId) {
  if (!message) return { ok: false, code: 'NOT_FOUND' };
  if (message.senderRole === 'system') return { ok: false, code: 'NOT_ALLOWED' };
  if (Number(message.senderUserId) === Number(userId)) {
    return { ok: false, code: 'NOT_ALLOWED' };
  }
  if (message.reportedAt) return { ok: true, already: true };
  return { ok: true };
}
