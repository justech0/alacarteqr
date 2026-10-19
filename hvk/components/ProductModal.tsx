import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, Star } from 'lucide-react';
import { Product } from '../types';
import { useApp } from '../context';

interface ProductModalProps {
  product: Product | null;
  onClose: () => void;
}

export const ProductModal: React.FC<ProductModalProps> = ({ product, onClose }) => {
  const { translate, language } = useApp();

  if (!product) return null;

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.16, ease: 'easeOut' }}
          onClick={onClose}
          className="absolute inset-0 bg-black/80 sm:bg-black/80 sm:backdrop-blur-sm"
        />

        <motion.div
          initial={{ opacity: 0, scale: 0.96, y: 10 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.96, y: 10 }}
          transition={{ duration: 0.16, ease: 'easeOut' }}
          className="relative w-full max-w-lg bg-white dark:bg-saray-surface border border-saray-gold/20 rounded-xl overflow-hidden shadow-2xl"
          onClick={(e) => e.stopPropagation()}
        >
          <button 
            onClick={onClose} 
            className="absolute top-4 right-4 z-10 bg-black/20 dark:bg-white/10 p-2 rounded-full text-white backdrop-blur-md hover:bg-saray-gold transition-colors"
          >
            <X size={24} />
          </button>

          <div className="relative h-72 w-full">
            <img
              src={product.image}
              alt={product.name[language]}
              className="w-full h-full object-cover"
              loading="lazy"
              decoding="async"
              width={960}
              height={720}
            />
            <div className="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
            {product.isPopular && (
              <div className="absolute bottom-4 left-4 flex items-center gap-2 text-saray-gold font-bold bg-black/60 px-3 py-1.5 rounded-full backdrop-blur-md border border-saray-gold/30">
                <Star size={16} fill="#0B2D4D" />
                <span className="text-xs uppercase tracking-wider">{translate('popular')}</span>
              </div>
            )}
          </div>

          <div className="p-8 text-center">
            <h2 className="font-serif text-3xl text-stone-900 dark:text-saray-gold mb-3 leading-tight">{product.name[language]}</h2>
            <div className="w-12 h-1 bg-saray-gold mx-auto mb-6 rounded-full"></div>
            
            <p className="text-stone-600 dark:text-saray-muted font-sans leading-relaxed mb-8 text-lg">
              {product.description[language]}
            </p>

            <div className="inline-block border-2 border-saray-gold px-8 py-3 rounded-xl bg-saray-gold/5">
              <span className="font-serif text-3xl text-stone-900 dark:text-white font-bold">
                {product.price} <span className="text-lg text-saray-gold">{translate('currency')}</span>
              </span>
            </div>
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  );
};