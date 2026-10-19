import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, Star, Send, MessageSquare } from 'lucide-react';
import { useApp } from '../context';
import { Branch } from '../types';

export const FeedbackModal: React.FC<{ isOpen: boolean; onClose: () => void; branches: Branch[] }> = ({ isOpen, onClose, branches: _branches }) => {
  const { translate, language } = useApp();
  const [step, setStep] = useState<1 | 2 | 3>(1); // 1: Topic, 2: Rating, 3: Form
  const [topic, setTopic] = useState<string>('');
  const [rating, setRating] = useState<number | null>(null);
  const [comment, setComment] = useState('');
  const [isRecoveryMode, setIsRecoveryMode] = useState(false);
  const [contact, setContact] = useState('');
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [showImagePicker, setShowImagePicker] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [error, setError] = useState('');

  const topics = [
    { id: 'taste', label: translate('topicTaste') || 'LEZZET VE KALİTE' },
    { id: 'service', label: translate('topicService') || 'HİZMET HIZINIZ' },
    { id: 'staff', label: translate('topicStaff') || 'PERSONEL' },
    { id: 'hygiene', label: translate('topicHygiene') || 'HİJYEN' },
    { id: 'other', label: translate('topicOther') || 'DİĞER (GÖRÜŞ ÖNERİ)' },
  ];
  const handleTopicSelect = (t: string) => {
    setTopic(t);
    setStep(2);
  };

  const handleRating = (r: number) => {
    setRating(r);
    if (r <= 2) {
      setIsRecoveryMode(true);
    } else {
      setIsRecoveryMode(false);
    }
    setStep(3);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!rating || comment.trim() === '') {
      setError(translate('feedbackValidation'));
      return;
    }

    setIsSending(true);
    setError('');

    const resolveApiUrl = (path: string) => {
      const clean = path.replace(/^\//, '');
      if (typeof window === 'undefined') return `/${clean}`;
      try {
        return new URL(`./${clean}`, window.location.href).toString();
      } catch (err) {
        return `/${clean}`;
      }
    };

    try {
      const formData = new FormData();
      formData.append('topic', topic);
      formData.append('rating', String(rating));
      formData.append('comment', comment);
      formData.append('contact', contact);
      formData.append('language', language);
      if (imageFile) {
        formData.append('image', imageFile);
      }

      const response = await fetch(resolveApiUrl('admin/api/feedback.php'), {
        method: 'POST',
        body: formData,
      });

      const payload = await response.json();
      if (!response.ok || payload.error) {
        throw new Error(payload.error || translate('saveError'));
      }

      setStep(1);
      setTopic('');
      setRating(null);
      setComment('');
      setContact('');
      setImageFile(null);
      setImagePreview(null);
      setShowImagePicker(false);
      onClose();
      alert(translate('feedbackSuccess'));
    } catch (err: any) {
      setError(err.message || translate('genericError'));
    } finally {
      setIsSending(false);
    }
  };

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) {
      setImageFile(null);
      setImagePreview(null);
      return;
    }
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
      setError(translate('imageTypeError'));
      return;
    }
    setImageFile(file);
    setImagePreview(URL.createObjectURL(file));
  };

  if (!isOpen) return null;

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4">
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          onClick={onClose}
          transition={{ duration: 0.16, ease: 'easeOut' }}
          className="absolute inset-0 bg-black/80 sm:bg-black/80 sm:backdrop-blur-sm"
        />

        <motion.div
          initial={{ y: "100%", opacity: 0.9 }}
          animate={{ y: 0, opacity: 1 }}
          exit={{ y: "100%", opacity: 0.9 }}
          transition={{ duration: 0.16, ease: 'easeOut' }}
          className="relative w-full max-w-md bg-white dark:bg-saray-surface border-t-2 sm:border-2 border-saray-gold/20 rounded-t-2xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
        >
          {/* Header */}
          <div className="bg-stone-100 dark:bg-saray-black/50 p-4 border-b border-black/5 dark:border-white/5 flex justify-between items-center sticky top-0 z-10">
            <h3 className="font-serif text-lg text-saray-gold">{translate('feedbackTitle')}</h3>
            <button onClick={onClose} className="text-stone-500 dark:text-saray-muted hover:text-black dark:hover:text-white"><X size={20} /></button>
          </div>

          <div className="p-6 overflow-y-auto bg-stone-50 dark:bg-transparent custom-scrollbar">
            
            {/* Step 0: Branch Selection */}

            {/* Step 1: Topic Selection */}
            {step === 1 && (
              <motion.div initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ duration: 0.15, ease: 'easeOut' }}>
                <p className="text-center text-stone-700 dark:text-saray-text mb-6 font-sans">{translate('selectTopic')}</p>
                <div className="space-y-3">
                  {topics.map((t) => (
                    <button
                      key={t.id}
                      onClick={() => handleTopicSelect(t.id)}
                      className="w-full p-4 rounded-xl bg-saray-olive text-white font-bold hover:bg-opacity-90 transition-all text-center shadow-md border border-white/10"
                    >
                      {t.label}
                    </button>
                  ))}
                </div>
              </motion.div>
            )}

            {/* Step 2: Rating */}
            {step === 2 && (
              <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} transition={{ duration: 0.15, ease: 'easeOut' }} className="text-center py-8">
                <p className="text-lg font-serif text-stone-800 dark:text-saray-text mb-8">{translate('rateUs')}</p>
                <div className="flex justify-center gap-2 mb-8">
                  {[1, 2, 3, 4, 5].map((r) => (
                    <button 
                      key={r}
                      onClick={() => handleRating(r)}
                      className="p-2 hover:scale-105 transition-transform duration-150"
                    >
                      <Star 
                        size={36} 
                        fill={rating && rating >= r ? "#0B2D4D" : "transparent"} 
                        className={rating && rating >= r ? "text-saray-gold" : "text-stone-300 dark:text-stone-600"}
                      />
                    </button>
                  ))}
                </div>
                 <button onClick={() => setStep(1)} className="mt-4 w-full text-center text-xs text-stone-400 underline py-2">
                    {translate('back')}
                </button>
              </motion.div>
            )}

            {/* Step 3: Message Form */}
            {step === 3 && (
              <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.15, ease: 'easeOut' }}>
                {isRecoveryMode && (
                  <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 p-4 rounded-xl mb-6 flex items-start gap-3">
                    <div className="p-2 bg-red-100 dark:bg-red-900/40 rounded-full text-red-600 dark:text-red-400">
                         <MessageSquare size={16} />
                    </div>
                    <div>
                        <p className="font-bold text-red-800 dark:text-red-300 text-sm mb-1">{translate('sorry')}</p>
                        <p className="text-xs text-red-700 dark:text-red-200">{translate('recoveryMessage')}</p>
                    </div>
                  </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-4">
                  {error && (
                    <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-sm text-red-200">{error}</div>
                  )}
                  <textarea
                    rows={4}
                    value={comment}
                    onChange={(e) => setComment(e.target.value)}
                    placeholder={translate('yourMessage')}
                    className="w-full p-4 rounded-xl bg-white dark:bg-black/20 border border-stone-200 dark:border-white/10 text-stone-800 dark:text-saray-text placeholder-stone-400 focus:border-saray-gold outline-none resize-none"
                  ></textarea>
                  
                  <input
                    type="text"
                    value={contact}
                    onChange={(e) => setContact(e.target.value)}
                    placeholder={translate('contactDetails')}
                    className="w-full p-4 rounded-xl bg-white dark:bg-black/20 border border-stone-200 dark:border-white/10 text-stone-800 dark:text-saray-text placeholder-stone-400 focus:border-saray-gold outline-none"
                  />

                  <div className="space-y-2">
                    <button
                      type="button"
                      onClick={() => setShowImagePicker((prev) => !prev)}
                      className="text-[11px] font-semibold text-saray-gold hover:text-saray-darkGold underline-offset-2 underline"
                    >
                      {translate('attachImage')}
                    </button>
                    {showImagePicker && (
                      <div className="flex items-center gap-3">
                        <label className="flex-1 cursor-pointer rounded-xl border border-dashed border-saray-gold/40 bg-white dark:bg-black/20 px-4 py-3 text-xs text-saray-gold hover:border-saray-gold/80 transition-all">
                          <input type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={handleImageChange} />
                          <span className="font-semibold">{translate('chooseImage')}</span>
                        </label>
                        {imagePreview && (
                          <div className="relative h-14 w-14 rounded-lg overflow-hidden border border-saray-gold/40 bg-black/30">
                            <img
                              src={imagePreview}
                              alt="Önizleme"
                              className="w-full h-full object-contain"
                              loading="lazy"
                              decoding="async"
                              width={320}
                              height={240}
                            />
                            <button
                              type="button"
                              onClick={() => { setImageFile(null); setImagePreview(null); }}
                              className="absolute -top-2 -right-2 bg-black text-white rounded-full w-5 h-5 text-[10px]"
                            >
                              ×
                            </button>
                          </div>
                        )}
                      </div>
                    )}
                  </div>

                  <button
                    type="submit"
                    disabled={isSending}
                    className="w-full py-4 bg-saray-gold disabled:bg-saray-gold/60 disabled:cursor-not-allowed text-saray-black font-bold rounded-xl shadow-lg hover:bg-saray-darkGold transition-colors flex items-center justify-center gap-2"
                  >
                    <span>{isSending ? translate('sending') : translate('submit')}</span>
                    <Send size={18} className={isSending ? 'animate-pulse' : ''} />
                  </button>
                </form>
              </motion.div>
            )}
            
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  );
};
