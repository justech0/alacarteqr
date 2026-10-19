import React, { useState } from 'react';
import { useApp } from '../context';
import { motion, AnimatePresence } from 'framer-motion';
import { Globe } from 'lucide-react';

export const LanguageSwitcher: React.FC = () => {
  const { language, setLanguage } = useApp();
  const [isOpen, setIsOpen] = useState(false);

  const langs = [
    { code: 'tr', flag: '🇹🇷', label: 'TR' },
    { code: 'en', flag: '🇬🇧', label: 'EN' },
    { code: 'ar', flag: '🇸🇦', label: 'AR' }
  ] as const;

  return (
    <div className="relative z-50">
      <button 
        onClick={() => setIsOpen(!isOpen)}
        className="p-2 rounded-full bg-white/10 dark:bg-black/20 hover:bg-saray-gold/20 text-saray-gold transition-colors backdrop-blur-sm border border-transparent hover:border-saray-gold/50"
      >
        <Globe size={24} />
        <span className="absolute -top-1 -right-1 flex h-3 w-3">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-saray-gold opacity-75"></span>
            <span className="relative inline-flex rounded-full h-3 w-3 bg-saray-gold"></span>
        </span>
      </button>

      <AnimatePresence>
        {isOpen && (
          <motion.div 
            initial={{ opacity: 0, y: -10, scale: 0.9 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -10, scale: 0.9 }}
            className="absolute right-0 top-12 bg-white dark:bg-saray-surface rounded-lg shadow-xl border border-black/5 dark:border-white/10 overflow-hidden min-w-[120px]"
          >
            {langs.map((lang) => (
              <button
                key={lang.code}
                onClick={() => {
                  setLanguage(lang.code);
                  setIsOpen(false);
                }}
                className={`w-full flex items-center gap-3 px-4 py-3 text-sm font-bold transition-colors ${
                  language === lang.code 
                    ? 'bg-saray-gold/10 text-saray-gold' 
                    : 'text-stone-700 dark:text-saray-muted hover:bg-black/5 dark:hover:bg-white/5'
                }`}
              >
                <span className="text-lg">{lang.flag}</span>
                <span>{lang.label}</span>
              </button>
            ))}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
};