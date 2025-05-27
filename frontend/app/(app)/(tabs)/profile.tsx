import React from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/ThemedText';
import { Button } from '@/components/ui/Button';
import { IconSymbol } from '@/components/ui/IconSymbol';
import { useAuth } from '@/hooks/useAuth';
import { useThemeColor } from '@/hooks/useThemeColor';

export default function ProfileScreen() {
  const { user, logout, isPremium, isAdmin } = useAuth();
  const backgroundColor = useThemeColor({}, 'background');
  const textColor = useThemeColor({}, 'text');
  const cardColor = useThemeColor({ light: '#F8F9FA', dark: '#1F2937' }, 'background');

  const handleLogout = async () => {
    Alert.alert(
      'Déconnexion',
      'Êtes-vous sûr de vouloir vous déconnecter ?',
      [
        { text: 'Annuler', style: 'cancel' },
        { 
          text: 'Déconnexion', 
          style: 'destructive',
          onPress: async () => {
            await logout();
            // ✅ PAS de redirection manuelle ici non plus
            // La redirection se fera automatiquement vers (auth)
          }
        },
      ]
    );
  };

  const profileStats = [
    { label: 'Défis créés', value: user?.created_challenges_count || 0, icon: 'plus.circle' },
    { label: 'Participations', value: user?.participations_count || 0, icon: 'target' },
    { label: 'Amis', value: 0, icon: 'people' }, // À implémenter
  ];

  return (
    <SafeAreaView style={[styles.container, { backgroundColor }]}>
      <ScrollView contentContainerStyle={styles.scrollContent}>
        
        {/* Header Profil */}
        <View style={[styles.profileHeader, { backgroundColor: cardColor }]}>
          <View style={styles.avatarContainer}>
            {user?.avatar ? (
              <Text>Avatar à implémenter</Text>
            ) : (
              <View style={styles.avatarPlaceholder}>
                <IconSymbol name="person" size={48} color="#FFFFFF" />
              </View>
            )}
          </View>
          
          <View style={styles.profileInfo}>
            <ThemedText type="title" style={styles.userName}>
              {user?.name || 'Utilisateur'}
            </ThemedText>
            <ThemedText style={[styles.userEmail, { color: textColor }]}>
              {user?.email}
            </ThemedText>
            
            <View style={styles.badges}>
              {isPremium && (
                <View style={styles.premiumBadge}>
                  <Text style={styles.badgeText}>✨ Premium</Text>
                </View>
              )}
              {isAdmin && (
                <View style={styles.adminBadge}>
                  <Text style={styles.badgeText}>👑 Admin</Text>
                </View>
              )}
            </View>
          </View>
        </View>

        {/* Statistiques */}
        <View style={[styles.statsSection, { backgroundColor: cardColor }]}>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            Statistiques
          </ThemedText>
          
          <View style={styles.statsGrid}>
            {profileStats.map((stat, index) => (
              <View key={index} style={styles.statCard}>
                <IconSymbol 
                  name={stat.icon as any} 
                  size={24} 
                  color="#FF4B8B" 
                />
                <Text style={styles.statValue}>{stat.value}</Text>
                <Text style={[styles.statLabel, { color: textColor }]}>
                  {stat.label}
                </Text>
              </View>
            ))}
          </View>
        </View>

        {/* Bio */}
        {user?.bio && (
          <View style={[styles.bioSection, { backgroundColor: cardColor }]}>
            <ThemedText type="subtitle" style={styles.sectionTitle}>
              À propos
            </ThemedText>
            <ThemedText style={[styles.bioText, { color: textColor }]}>
              {user.bio}
            </ThemedText>
          </View>
        )}

        {/* Informations du compte */}
        <View style={[styles.accountSection, { backgroundColor: cardColor }]}>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            Informations du compte
          </ThemedText>
          
          <View style={styles.infoRow}>
            <Text style={[styles.infoLabel, { color: textColor }]}>
              Membre depuis
            </Text>
            <Text style={[styles.infoValue, { color: textColor }]}>
              {user?.created_at ? new Date(user.created_at).toLocaleDateString('fr-FR') : 'N/A'}
            </Text>
          </View>
          
          {isPremium && user?.premium_until && (
            <View style={styles.infoRow}>
              <Text style={[styles.infoLabel, { color: textColor }]}>
                Premium jusqu'au
              </Text>
              <Text style={[styles.infoValue, { color: '#FFD700' }]}>
                {new Date(user.premium_until).toLocaleDateString('fr-FR')}
              </Text>
            </View>
          )}
        </View>

        {/* Actions */}
        <View style={styles.actionsSection}>
          <Button
            title="Modifier le profil"
            variant="outline"
            onPress={() => {
              Alert.alert('Info', 'Fonctionnalité en développement');
            }}
            style={styles.actionButton}
          />
          
          {!isPremium && (
            <Button
              title="✨ Passer Premium"
              variant="primary"
              onPress={() => {
                Alert.alert('Premium', 'Fonctionnalité en développement');
              }}
              style={styles.actionButton}
            />
          )}
          
          <Button
            title="Paramètres"
            variant="secondary"
            onPress={() => {
              Alert.alert('Paramètres', 'Fonctionnalité en développement');
            }}
            style={styles.actionButton}
          />
          
          <Button
            title="Déconnexion"
            variant="outline"
            onPress={handleLogout}
            style={[styles.actionButton, styles.logoutButton]}
            textStyle={{ color: '#EF4444' }}
          />
        </View>
        
      </ScrollView>
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