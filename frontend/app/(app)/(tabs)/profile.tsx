// app/(app)/(tabs)/profile.tsx
import React from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/ThemedText';
import { Button } from '@/components/ui/Button';
import { IconSymbol } from '@/components/ui/IconSymbol';
import { useAuth } from '@/hooks/useAuth';
import { useThemeColor } from '@/hooks/useThemeColor';
import { useRouter } from 'expo-router';

export default function ProfileScreen() {
  const { user, logout, isPremium, isAdmin } = useAuth();
  const backgroundColor = useThemeColor({}, 'background');
  const textColor = useThemeColor({}, 'text');
  const cardColor = useThemeColor({ light: '#F8F9FA', dark: '#1F2937' }, 'background');
  const router = useRouter();

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
            try {
              console.log('🔄 Starting logout process...');
              await logout();
              console.log('✅ Logout successful, redirecting to login...');
              
              // 🔥 REDIRECTION EXPLICITE APRÈS DÉCONNEXION
              router.replace('/(auth)/login');
              
            } catch (error: any) {
              console.error('❌ Logout error:', error);
              Alert.alert('Erreur', 'Erreur lors de la déconnexion');
              
              // Forcer la redirection même en cas d'erreur
              router.replace('/(auth)/login');
            }
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
  scrollContent: {
    paddingHorizontal: 20,
    paddingVertical: 16,
  },
  profileHeader: {
    flexDirection: 'row',
    padding: 20,
    borderRadius: 16,
    marginBottom: 24,
  },
  avatarContainer: {
    marginRight: 16,
  },
  avatarPlaceholder: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: '#FF4B8B',
    alignItems: 'center',
    justifyContent: 'center',
  },
  profileInfo: {
    flex: 1,
    justifyContent: 'center',
  },
  userName: {
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  userEmail: {
    fontSize: 16,
    opacity: 0.7,
    marginBottom: 8,
  },
  badges: {
    flexDirection: 'row',
    gap: 8,
  },
  premiumBadge: {
    backgroundColor: '#FFD700',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
  },
  adminBadge: {
    backgroundColor: '#FF4B8B',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
  },
  badgeText: {
    color: '#161D3F',
    fontSize: 12,
    fontWeight: '600',
  },
  statsSection: {
    padding: 20,
    borderRadius: 16,
    marginBottom: 24,
  },
  sectionTitle: {
    marginBottom: 16,
    fontWeight: '600',
  },
  statsGrid: {
    flexDirection: 'row',
    justifyContent: 'space-around',
  },
  statCard: {
    alignItems: 'center',
  },
  statValue: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#FF4B8B',
    marginTop: 8,
  },
  statLabel: {
    fontSize: 14,
    marginTop: 4,
    opacity: 0.7,
  },
  bioSection: {
    padding: 20,
    borderRadius: 16,
    marginBottom: 24,
  },
  bioText: {
    fontSize: 16,
    lineHeight: 24,
  },
  accountSection: {
    padding: 20,
    borderRadius: 16,
    marginBottom: 24,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
  },
  infoLabel: {
    fontSize: 16,
  },
  infoValue: {
    fontSize: 16,
    fontWeight: '500',
  },
  actionsSection: {
    gap: 12,
  },
  actionButton: {
    marginBottom: 4,
  },
  logoutButton: {
    borderColor: '#EF4444',
    marginTop: 8,
  },
});