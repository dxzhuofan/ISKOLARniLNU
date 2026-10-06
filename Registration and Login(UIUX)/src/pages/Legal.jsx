import AuthCard from '../components/AuthCard';

const PAGES = {
  privacy: { title: 'Data Privacy Notice', body: 'The portal collects the personal information you provide (such as your name, Student ID, contact details and uploaded documents) to process scholarship applications.' },
  terms: { title: 'Terms of Use', body: 'These terms describe the rules for using the LNU Scholarship Management Portal.' },
};

function Legal({ kind }) {
  const { title, body } = PAGES[kind];
  return (
    <AuthCard title={title} subtitle="Leyte Normal University">
      <p className="text-slate-700">{body}</p>
      <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Placeholder: the official text must be supplied by the university (for example, its Data Protection Officer) before launch.
      </p>
    </AuthCard>
  );
}

export const PrivacyNotice = () => <Legal kind="privacy" />;
export const TermsOfUse = () => <Legal kind="terms" />;
