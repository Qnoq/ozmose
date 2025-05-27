// app/(auth)/login.tsx
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useAuth } from '@/hooks/useAuth';
import { useThemeColor } from '@/hooks/useThemeColor';
import { Link, useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  View
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function LoginScreen() {
  const { login, isLoading, error, clearError, isAuthenticated, isInitialized } = useAuth();
  const backgroundColor = useThemeColor({}, 'background');
  const textColor = useThemeColor({}, 'text');
  const router = useRouter();
  
  const [formData, setFormData] = useState({
    email: '',
    password: '',
  });
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  // 🔥 REDIRECTION AUTOMATIQUE SI DÉJÀ CONNECTÉ
  useEffect(() => {
    if (isInitialized && isAuthenticated) {
      console.log('✅ Already authenticated, redirecting to app');
      router.replace('/(app)/(tabs)');
    }
  }, [isAuthenticated, isInitialized]);

  const validateForm = () => {
    const errors: Record<string, string> = {};

    if (!formData.email) {
      errors.email = 'L\'email est requis';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      errors.email = 'Format d\'email invalide';
    }

    if (!formData.password) {
      errors.password = 'Le mot de passe est requis';
    } else if (formData.password.length < 6) {
      errors.password = 'Le mot de passe doit contenir au moins 6 caractères';
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleLogin = async () => {
    if (!validateForm()) return;
  
    try {
      clearError();
      console.log('🔄 Attempting login...');
      
      await login(formData);
      
      console.log('✅ Login successful, redirecting...');
      // La redirection se fera automatiquement via useEffect
      
    } catch (error: any) {
      console.error('❌ Login failed:', error.message);
      Alert.alert('Erreur de connexion', error.message);
    }
  };

  const handleInputChange = (field: string, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    if (formErrors[field]) {
      setFormErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  // Ne pas afficher l'écran si déjà connecté
  if (isInitialized && isAuthenticated) {
    return null;
  }

  return (
    <SafeAreaView style={[styles.container, { backgroundColor }]}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.keyboardAvoid}>
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled">
          
          <View style={styles.header}>
            <Text style={[styles.title, { color: textColor }]}>
              Bienvenue sur
            </Text>
            <Text style={[styles.appName, { color: '#FF4B8B' }]}>
              Ozmose
            </Text>
            <Text style={[styles.subtitle, { color: textColor }]}>
              Connectez-vous pour découvrir des défis passionnants
            </Text>
          </View>

          <View style={styles.form}>
            <Input
              label="Email"
              placeholder="votre@email.com"
              value={formData.email}
              onChangeText={(value) => handleInputChange('email', value)}
              error={formErrors.email}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              leftIcon="envelope"
              required
            />

            <Input
              label="Mot de passe"
              placeholder="Votre mot de passe"
              value={formData.password}
              onChangeText={(value) => handleInputChange('password', value)}
              error={formErrors.password}
              secureTextEntry
              autoCapitalize="none"
              autoComplete="password"
              leftIcon="lock"
              required
            />

            {error && (
              <View style={styles.errorContainer}>
                <Text style={styles.errorText}>{error}</Text>
              </View>
            )}

            <Button
              title="Se connecter"
              onPress={handleLogin}
              loading={isLoading}
              disabled={isLoading}
              variant="primary"
              size="large"
              style={styles.loginButton}
            />

            <Text style={styles.forgotPassword}>
              Mot de passe oublié ?
            </Text>
          </View>

          <View style={styles.footer}>
            <Text style={[styles.footerText, { color: textColor }]}>
              Pas encore de compte ?
            </Text>
            <Link href="/(auth)/register" asChild>
              <Text style={styles.registerLink}>
                S'inscrire
              </Text>
            </Link>
          </View>

          {__DEV__ && (
            <View style={styles.devButtons}>
              <Button
                title="Connexion test"
                onPress={() => {
                  setFormData({
                    email: 'test@ozmose.app',
                    password: 'password123',
                  });
                }}
                variant="outline"
                size="small"
              />
            </View>
          )}
          
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  keyboardAvoid: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: 24,
    paddingVertical: 32,
  },
  header: {
    alignItems: 'center',
    marginBottom: 48,
    marginTop: 32,
  },
  title: {
    fontSize: 28,
    fontWeight: '300',
    textAlign: 'center',
  },
  appName: {
    fontSize: 42,
    fontWeight: 'bold',
    textAlign: 'center',
    marginVertical: 8,
  },
  subtitle: {
    fontSize: 16,
    textAlign: 'center',
    opacity: 0.7,
    marginTop: 8,
  },
  form: {
    flex: 1,
    justifyContent: 'center',
  },
  errorContainer: {
    backgroundColor: '#FEE2E2',
    padding: 12,
    borderRadius: 8,
    marginBottom: 16,
  },
  errorText: {
    color: '#DC2626',
    fontSize: 14,
    textAlign: 'center',
  },
  loginButton: {
    marginTop: 8,
    marginBottom: 16,
  },
  forgotPassword: {
    color: '#FF4B8B',
    fontSize: 16,
    fontWeight: '500',
    textAlign: 'center',
    textDecorationLine: 'underline',
  },
  footer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 32,
    gap: 8,
  },
  footerText: {
    fontSize: 16,
  },
  registerLink: {
    color: '#FF4B8B',
    fontSize: 16,
    fontWeight: '600',
    textDecorationLine: 'underline',
  },
  devButtons: {
    marginTop: 24,
    opacity: 0.7,
  },
});