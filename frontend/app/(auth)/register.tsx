import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useAuth } from '@/hooks/useAuth';
import { useThemeColor } from '@/hooks/useThemeColor';
import { Link, router } from 'expo-router';
import React, { useState } from 'react';
import {
    Alert,
    KeyboardAvoidingView,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function RegisterScreen() {
  const { register, isLoading, error, clearError } = useAuth();
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [acceptTerms, setAcceptTerms] = useState(false);

  const backgroundColor = useThemeColor({}, 'background');
  const textColor = useThemeColor({}, 'text');

  const validateForm = () => {
    const errors: Record<string, string> = {};

    if (!formData.name) {
      errors.name = 'Le nom est requis';
    } else if (formData.name.length < 2) {
      errors.name = 'Le nom doit contenir au moins 2 caractères';
    }

    if (!formData.email) {
      errors.email = 'L\'email est requis';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      errors.email = 'Format d\'email invalide';
    }

    if (!formData.password) {
      errors.password = 'Le mot de passe est requis';
    } else if (formData.password.length < 8) {
      errors.password = 'Le mot de passe doit contenir au moins 8 caractères';
    } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(formData.password)) {
      errors.password = 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre';
    }

    if (!formData.password_confirmation) {
      errors.password_confirmation = 'La confirmation du mot de passe est requise';
    } else if (formData.password !== formData.password_confirmation) {
      errors.password_confirmation = 'Les mots de passe ne correspondent pas';
    }

    if (!acceptTerms) {
      errors.terms = 'Vous devez accepter les conditions d\'utilisation';
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleRegister = async () => {
    if (!validateForm()) return;

    try {
      clearError();
      await register(formData);
      // Navigation sera gérée automatiquement par le système d'auth
      router.replace('/(app)/(tabs)');
    } catch (error: any) {
      Alert.alert('Erreur d\'inscription', error.message);
    }
  };

  const handleInputChange = (field: string, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Effacer l'erreur du champ si elle existe
    if (formErrors[field]) {
      setFormErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  const getPasswordStrength = () => {
    const password = formData.password;
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    return strength;
  };

  const getPasswordStrengthText = () => {
    const strength = getPasswordStrength();
    switch (strength) {
      case 0:
      case 1:
        return { text: 'Très faible', color: '#EF4444' };
      case 2:
        return { text: 'Faible', color: '#F59E0B' };
      case 3:
        return { text: 'Moyen', color: '#10B981' };
      case 4:
      case 5:
        return { text: 'Fort', color: '#059669' };
      default:
        return { text: '', color: '#6B7280' };
    }
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor }]}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.keyboardAvoid}>
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled">
          
          {/* Header */}
          <View style={styles.header}>
            <Text style={[styles.title, { color: textColor }]}>
              Rejoignez
            </Text>
            <Text style={[styles.appName, { color: '#FF4B8B' }]}>
              Ozmose
            </Text>
            <Text style={[styles.subtitle, { color: textColor }]}>
              Créez votre compte et commencez l'aventure
            </Text>
          </View>

          {/* Formulaire */}
          <View style={styles.form}>
            <Input
              label="Nom complet"
              placeholder="Votre nom complet"
              value={formData.name}
              onChangeText={(value) => handleInputChange('name', value)}
              error={formErrors.name}
              autoCapitalize="words"
              autoComplete="name"
              leftIcon="person"
              required
            />

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
              placeholder="Créez un mot de passe sécurisé"
              value={formData.password}
              onChangeText={(value) => handleInputChange('password', value)}
              error={formErrors.password}
              secureTextEntry
              autoCapitalize="none"
              leftIcon="lock"
              required
              hint={formData.password ? undefined : 'Au moins 8 caractères avec majuscule, minuscule et chiffre'}
            />

            {/* Indicateur de force du mot de passe */}
            {formData.password && (
              <View style={styles.passwordStrength}>
                <Text style={[styles.strengthText, { color: getPasswordStrengthText().color }]}>
                  Force: {getPasswordStrengthText().text}
                </Text>
                <View style={styles.strengthBar}>
                  {[...Array(5)].map((_, i) => (
                    <View
                      key={i}
                      style={[
                        styles.strengthSegment,
                        {
                          backgroundColor: i < getPasswordStrength() 
                            ? getPasswordStrengthText().color 
                            : '#E5E7EB'
                        }
                      ]}
                    />
                  ))}
                </View>
              </View>
            )}

            <Input
              label="Confirmer le mot de passe"
              placeholder="Confirmez votre mot de passe"
              value={formData.password_confirmation}
              onChangeText={(value) => handleInputChange('password_confirmation', value)}
              error={formErrors.password_confirmation}
              secureTextEntry
              autoCapitalize="none"
              leftIcon="lock"
              required
            />

            {/* Conditions d'utilisation */}
            <View style={styles.termsContainer}>
              <Button
                title={acceptTerms ? "✓" : ""}
                onPress={() => {
                  setAcceptTerms(!acceptTerms);
                  if (formErrors.terms) {
                    setFormErrors(prev => ({ ...prev, terms: '' }));
                  }
                }}
                variant="outline"
                size="small"
                style={[styles.checkbox, acceptTerms && styles.checkboxChecked]}
              />
              <Text style={[styles.termsText, { color: textColor }]}>
                J'accepte les{' '}
                <Text style={styles.termsLink}>conditions d'utilisation</Text>
                {' '}et la{' '}
                <Text style={styles.termsLink}>politique de confidentialité</Text>
              </Text>
            </View>

            {formErrors.terms && (
              <Text style={styles.termsError}>{formErrors.terms}</Text>
            )}

            {error && (
              <View style={styles.errorContainer}>
                <Text style={styles.errorText}>{error}</Text>
              </View>
            )}

            <Button
              title="Créer mon compte"
              onPress={handleRegister}
              loading={isLoading}
              disabled={isLoading}
              variant="primary"
              size="large"
              style={styles.registerButton}
            />
          </View>

          {/* Connexion */}
          <View style={styles.footer}>
            <Text style={[styles.footerText, { color: textColor }]}>
              Déjà un compte ?
            </Text>
            <Link href="/(auth)/login" asChild>
              <Text style={styles.loginLink}>
                Se connecter
              </Text>
            </Link>
          </View>
          
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
    marginBottom: 32,
    marginTop: 16,
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
  },
  passwordStrength: {
    marginBottom: 16,
    marginTop: -8,
  },
  strengthText: {
    fontSize: 12,
    fontWeight: '500',
    marginBottom: 4,
  },
  strengthBar: {
    flexDirection: 'row',
    gap: 2,
  },
  strengthSegment: {
    flex: 1,
    height: 4,
    borderRadius: 2,
  },
  termsContainer: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 8,
    marginTop: 8,
  },
  checkbox: {
    width: 24,
    height: 24,
    marginRight: 12,
    marginTop: 2,
    minHeight: 24,
    paddingVertical: 0,
    paddingHorizontal: 0,
  },
  checkboxChecked: {
    backgroundColor: '#FF4B8B',
    borderColor: '#FF4B8B',
  },
  termsText: {
    flex: 1,
    fontSize: 14,
    lineHeight: 20,
  },
  termsLink: {
    color: '#FF4B8B',
    textDecorationLine: 'underline',
  },
  termsError: {
    color: '#FF6B6B',
    fontSize: 14,
    marginBottom: 16,
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
  registerButton: {
    marginTop: 16,
    marginBottom: 16,
  },
  footer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 24,
    gap: 8,
  },
  footerText: {
    fontSize: 16,
  },
  loginLink: {
    color: '#FF4B8B',
    fontSize: 16,
    fontWeight: '600',
    textDecorationLine: 'underline',
  },
});