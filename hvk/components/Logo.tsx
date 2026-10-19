import React from 'react';
import { motion } from 'framer-motion';
import { LOGO_URL } from '../constants';

type LogoProps = {
  className?: string;
  variant?: 'light' | 'dark';
  size?: 'lg' | 'sm';
};

export const Logo: React.FC<LogoProps> = ({ className, variant = 'light', size = 'lg' }) => {
  const height = size === 'lg' ? 'h-40 md:h-48' : 'h-14 md:h-16';
  const src = LOGO_URL || '/thk.png';
  const srcSet = `${src} 640w, ${src} 1024w`;
  const sizes = size === 'lg' ? '(max-width: 768px) 80vw, 480px' : '(max-width: 768px) 40vw, 200px';

  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.98 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.9, ease: 'easeOut' }}
      className={`flex items-center justify-center text-center ${className ?? ''}`}
    >
      <img
        src={src}
        srcSet={srcSet}
        sizes={sizes}
        alt="Hava Kuvvetleri Komutanligi"
        className={`${height} max-h-64 w-auto object-contain`}
        loading="lazy"
        decoding="async"
        width={size === 'lg' ? 480 : 200}
        height={size === 'lg' ? 320 : 120}
      />
    </motion.div>
  );
};