import React from 'react';
import { MessageSquare } from 'lucide-react';
import { useApp } from '../context';

export const FeedbackToggle: React.FC<{ onClick: () => void }> = ({ onClick }) => {
  const { translate } = useApp();
  return (
    <button
      onClick={onClick}
      className="fixed bottom-6 right-6 z-40 bg-white dark:bg-saray-surface border border-saray-gold/30 rounded-lg shadow-2xl overflow-hidden group flex items-center pr-4 pl-3 py-3 transition-transform hover:scale-[1.02]"
      aria-label={translate('feedbackTitle')}
    >
      <div className="bg-saray-gold/10 p-2 rounded-full mr-3 text-saray-gold">
        <MessageSquare size={20} />
      </div>
      <div className="flex flex-col items-start">
        <span className="text-[10px] text-stone-500 dark:text-saray-muted uppercase tracking-widest leading-none mb-1">{translate('writeUs')}</span>
        <span className="text-sm font-serif font-bold text-stone-900 dark:text-saray-text leading-none">{translate('feedbackTitle')}</span>
      </div>
    </button>
  );
};

export default FeedbackToggle;
