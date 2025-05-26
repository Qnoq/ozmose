import { useThemeColor } from '@/hooks/useThemeColor';
import React, { useState } from 'react';
import {
  StyleSheet,
  Text,
  TextInput,
  TextInputProps,
  TouchableOpacity,
  View,
} from 'react-native';
import { IconSymbol } from './IconSymbol';

interface InputProps extends TextInputProps {
  label?: string;
  error?: string;
  hint?: string;
  leftIcon?: string;
  rightIcon?: string;
  onRightIconPress?: () => void;
  containerStyle?: any;
  required?: boolean;
}

export function Input({
  label,
  error,
  hint,
  leftIcon,
  rightIcon,
  onRightIconPress,
  containerStyle,
  required = false,
  secureTextEntry,
  ...props
}: InputProps) {
  // ✅ IMPORTANT: Tous les hooks en premier, inconditionnellement
  const [isSecure, setIsSecure] = useState(secureTextEntry);
  const [isFocused, setIsFocused] = useState(false);
  const textColor = useThemeColor({}, 'text');
  const backgroundColor = useThemeColor({}, 'background');
  const mutedColor = useThemeColor({ light: '#9CA3AF', dark: '#6B7280' }, 'text');
  const lightBorderColor = useThemeColor({ light: '#E1E5E9', dark: '#374151' }, 'text');

  // Calculs dérivés après les hooks
  const borderColor = error
    ? '#FF6B6B'
    : isFocused
    ? '#FF4B8B'
    : lightBorderColor;

  const toggleSecureEntry = () => {
    setIsSecure(!isSecure);
  };

  const getRightIcon = () => {
    if (secureTextEntry) {
      return isSecure ? 'eye.slash' : 'eye';
    }
    return rightIcon;
  };

  const handleRightIconPress = () => {
    if (secureTextEntry) {
      toggleSecureEntry();
    } else if (onRightIconPress) {
      onRightIconPress();
    }
  };

  return (
    <View style={[styles.container, containerStyle]}>
      {label && (
        <Text style={[styles.label, { color: textColor }]}>
          {label}
          {required && <Text style={styles.required}> *</Text>}
        </Text>
      )}
      
      <View
        style={[
          styles.inputContainer,
          {
            backgroundColor: backgroundColor,
            borderColor: borderColor,
          },
          isFocused && { borderWidth: 2 },
          error && styles.errorBorder,
        ]}>
        {leftIcon && (
          <View style={styles.leftIconContainer}>
            <IconSymbol
              name={leftIcon as any}
              size={20}
              color={mutedColor}
            />
          </View>
        )}
        
        <TextInput
          style={[
            styles.input,
            {
              color: textColor,
              flex: 1,
            },
            leftIcon && { paddingLeft: 0 },
          ]}
          placeholderTextColor={mutedColor}
          secureTextEntry={isSecure}
          onFocus={() => setIsFocused(true)}
          onBlur={() => setIsFocused(false)}
          {...props}
        />
        
        {getRightIcon() && (
          <TouchableOpacity
            style={styles.rightIconContainer}
            onPress={handleRightIconPress}>
            <IconSymbol
              name={getRightIcon() as any}
              size={20}
              color={mutedColor}
            />
          </TouchableOpacity>
        )}
      </View>
      
      {error && (
        <Text style={styles.error}>{error}</Text>
      )}
      
      {hint && !error && (
        <Text style={[styles.hint, { color: mutedColor }]}>
          {hint}
        </Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginBottom: 16,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 8,
  },
  required: {
    color: '#FF6B6B',
  },
  inputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 16,
    minHeight: 48,
  },
  input: {
    fontSize: 16,
    paddingVertical: 12,
    flex: 1,
  },
  leftIconContainer: {
    marginRight: 12,
  },
  rightIconContainer: {
    marginLeft: 12,
    padding: 4,
  },
  errorBorder: {
    borderColor: '#FF6B6B',
    borderWidth: 1,
  },
  error: {
    color: '#FF6B6B',
    fontSize: 14,
    marginTop: 4,
  },
  hint: {
    fontSize: 14,
    marginTop: 4,
  },
});