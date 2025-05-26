/**
 * Couleurs de l'application Ozmose
 * Bleu #161D3F et Rose #FF4B8B
 */

const ozmoseBlue = '#161D3F';
const ozmosPink = '#FF4B8B';

export const Colors = {
  light: {
    text: '#11181C',
    background: '#fff',
    tint: ozmosPink,
    icon: '#687076',
    tabIconDefault: '#687076',
    tabIconSelected: ozmosPink,
    primary: ozmosPink,
    secondary: ozmoseBlue,
    accent: ozmosPink,
    border: '#E1E5E9',
    card: '#F8F9FA',
    success: '#10B981',
    warning: '#F59E0B', 
    error: '#EF4444',
    muted: '#6B7280',
  },
  dark: {
    text: '#ECEDEE',
    background: '#151718',
    tint: ozmosPink,
    icon: '#9BA1A6',
    tabIconDefault: '#9BA1A6',
    tabIconSelected: ozmosPink,
    primary: ozmosPink,
    secondary: ozmoseBlue,
    accent: ozmosPink,
    border: '#374151',
    card: '#1F2937',
    success: '#059669',
    warning: '#D97706',
    error: '#DC2626',
    muted: '#9CA3AF',
  },
  
  // Couleurs spécifiques Ozmose
  ozmose: {
    blue: ozmoseBlue,
    pink: ozmosPink,
    blueLight: '#2A3154',
    pinkLight: '#FF6B9D',
    gradient: {
      primary: [ozmosPink, ozmoseBlue],
      secondary: ['#FF6B9D', '#2A3154'],
    }
  }
};