// Fallback for using MaterialIcons on Android and web.

import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import { SymbolWeight } from 'expo-symbols';
import { ComponentProps } from 'react';
import { OpaqueColorValue, type StyleProp, type TextStyle } from 'react-native';

type IconSymbolName = 
  | 'house.fill'
  | 'paperplane.fill'
  | 'chevron.left.forwardslash.chevron.right'
  | 'chevron.right'
  | 'envelope'
  | 'lock'
  | 'person'
  | 'eye'
  | 'eye.slash'
  | 'target'
  | 'dice'
  | 'people'
  | 'person.circle'
  | 'plus.circle'
  | 'plus.circle.fill'
  | 'list.bullet.rectangle'
  | 'dice.fill'
  | 'gear'
  | 'bell'
  | 'star'
  | 'star.fill'
  | 'heart'
  | 'heart.fill'
  | 'share'
  | 'camera'
  | 'photo'
  | 'trash'
  | 'pencil'
  | 'checkmark'
  | 'xmark'
  | 'info.circle'
  | 'exclamationmark.triangle'
  | 'questionmark.circle'
  | 'play.fill'
  | 'pause.fill'
  | 'stop.fill'
  | 'forward.fill'
  | 'backward.fill';

type IconMapping = Record<IconSymbolName, ComponentProps<typeof MaterialIcons>['name']>;

/**
 * Add your SF Symbols to Material Icons mappings here.
 * - see Material Icons in the [Icons Directory](https://icons.expo.fyi).
 * - see SF Symbols in the [SF Symbols](https://developer.apple.com/sf-symbols/) app.
 */
const MAPPING = {
  // Navigation de base
  'house.fill': 'home',
  'paperplane.fill': 'send',
  'chevron.left.forwardslash.chevron.right': 'code',
  'chevron.right': 'chevron-right',
  
  // Authentification
  'envelope': 'email',
  'lock': 'lock',
  'person': 'person-outline',
  'eye': 'visibility',
  'eye.slash': 'visibility-off',
  
  // Navigation Ozmose
  'target': 'track-changes',
  'dice': 'casino',
  'people': 'people-outline',
  'person.circle': 'account-circle',
  
  // Actions et interface
  'plus.circle': 'add-circle',
  'plus.circle.fill': 'add-circle',
  'list.bullet.rectangle': 'list',
  'dice.fill': 'casino',
  
  // Autres icônes utiles
  'gear': 'settings',
  'bell': 'notifications',
  'star': 'star',
  'star.fill': 'star',
  'heart': 'favorite-border',
  'heart.fill': 'favorite',
  'share': 'share',
  'camera': 'camera-alt',
  'photo': 'photo',
  'trash': 'delete',
  'pencil': 'edit',
  'checkmark': 'check',
  'xmark': 'close',
  'info.circle': 'info',
  'exclamationmark.triangle': 'warning',
  'questionmark.circle': 'help',
  
  // Médias
  'play.fill': 'play-arrow',
  'pause.fill': 'pause',
  'stop.fill': 'stop',
  'forward.fill': 'skip-next',
  'backward.fill': 'skip-previous',
  
} as IconMapping;

/**
 * An icon component that uses native SF Symbols on iOS, and Material Icons on Android and web.
 * This ensures a consistent look across platforms, and optimal resource usage.
 * Icon `name`s are based on SF Symbols and require manual mapping to Material Icons.
 */
export function IconSymbol({
  name,
  size = 24,
  color,
  style,
}: {
  name: IconSymbolName;
  size?: number;
  color: string | OpaqueColorValue;
  style?: StyleProp<TextStyle>;
  weight?: SymbolWeight;
}) {
  // Vérifier si l'icône existe dans le mapping
  const iconName = MAPPING[name];
  
  if (!iconName) {
    console.warn(`Icon "${name}" not found in mapping. Add it to IconSymbol.tsx`);
    // Fallback vers une icône par défaut
    return <MaterialIcons color={color} size={size} name="help" style={style} />;
  }
  
  return <MaterialIcons color={color} size={size} name={iconName} style={style} />;
}