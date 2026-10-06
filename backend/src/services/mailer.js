// Placeholder mailer. Replace sendPasswordReset with a real email provider (SMTP, SES, etc.).
export const mail = {
  async sendPasswordReset({ to, link }) {
    if (process.env.NODE_ENV === 'production') {
      console.warn('[mailer] No email provider configured; reset email NOT sent.');
      return;
    }
    console.log(`\n[mailer] Password reset link for ${to}:\n  ${link}\n`);
  },
};
