export class MatchingError extends Error {
  constructor(code, details = {}) {
    super(code);
    this.name = 'MatchingError';
    this.code = code;
    this.details = details;
  }
}
