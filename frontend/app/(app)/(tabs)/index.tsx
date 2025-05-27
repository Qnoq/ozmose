import { router } from 'expo-router';
import React from 'react';
import { ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/ThemedText';
import { Button } from '@/components/ui/Button';
import { IconSymbol } from '@/components/ui/IconSymbol';
import { useAuth } from '@/contexts/AuthContext'; // 🔥 CHANGEMENT D'IMPORT
import { useThemeColor } from '@/hooks/useThemeColor';

export default function HomeScreen() {
  const { user, logout } = useAuth(); // 🔥 SIMPLIFICATION
  const backgroundColor = useThemeColor({}, 'background');
  const textColor = useThemeColor({}, 'text');
  const cardColor = useThemeColor({ light: '#F8F9FA', dark: '#1F2937' }, 'background');

  // 🔥 CALCUL LOCAL DES PROPRIÉTÉS
  const isPremium = user?.is_premium || false;

  // 🔥 DÉCONNEXION SIMPLIFIÉE SANS REDIRECTION
  const handleLogout = async () => {
    try {
      await logout();
      // Pas de redirection manuelle ! 
      // Le RootNavigator détecte automatiquement que user = null et affiche AuthNavigator
    } catch (error) {
      console.error('Logout error:', error);
    }
  };

  const quickActions = [
    {
      title: 'Créer un défi',
      subtitle: 'Lancez un nouveau défi',
      icon: 'plus.circle.fill',
      color: '#FF4B8B',
      onPress: () => router.push('/challenge/create'),
    },
    {
      title: 'Action ou Vérité',
      subtitle: 'Démarrer une partie',
      icon: 'dice.fill',
      color: '#161D3F',
      onPress: () => router.push('/truth-or-dare'),
    },
    {
      title: 'Mes défis',
      subtitle: 'Voir vos défis actifs',
      icon: 'list.bullet.rectangle',
      color: '#10B981',
      onPress: () => router.push('/challenges'),
    },
  ];

  const stats = [
    { label: 'Défis créés', value: user?.created_challenges_count || 0 },
    { label: 'Participations', value: user?.participations_count || 0 },
    { label: 'Points', value: '0' }, // À implémenter avec l'API
  ];

  return (
    <SafeAreaView style={[styles.container, { backgroundColor }]}>
      <ScrollView contentContainerStyle={styles.scrollContent}>
        
        {/* Header avec salutation */}
        <View style={styles.header}>
          <View>
            <ThemedText style={styles.greeting}>
              Salut {user?.name?.split(' ')[0] || 'Utilisateur'} ! 👋
            </ThemedText>
            <ThemedText style={[styles.subtitle, { color: textColor }]}>
              Prêt pour de nouveaux défis ?
            </ThemedText>
          </View>
          
          {isPremium && (
            <View style={styles.premiumBadge}>
              <Text style={styles.premiumText}>✨ Premium</Text>
            </View>
          )}
        </View>

        {/* Statistiques rapides */}
        <View style={[styles.statsContainer, { backgroundColor: cardColor }]}>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            Vos statistiques
          </ThemedText>
          <View style={styles.statsGrid}>
            {stats.map((stat, index) => (
              <View key={index} style={styles.statItem}>
                <Text style={styles.statValue}>{stat.value}</Text>
                <Text style={[styles.statLabel, { color: textColor }]}>
                  {stat.label}
                </Text>
              </View>
            ))}
          </View>
        </View>

        {/* Actions rapides */}
        <View style={styles.actionsSection}>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            Actions rapides
          </ThemedText>
          
          {quickActions.map((action, index) => (
            <TouchableOpacity
              key={index}
              style={[styles.actionCard, { backgroundColor: cardColor }]}
              onPress={action.onPress}
              activeOpacity={0.7}>
              
              <View style={[styles.actionIcon, { backgroundColor: action.color + '20' }]}>
                <IconSymbol 
                  name={action.icon as any} 
                  size={24} 
                  color={action.color} 
                />
              </View>
              
              <View style={styles.actionContent}>
                <Text style={[styles.actionTitle, { color: textColor }]}>
                  {action.title}
                </Text>
                <Text style={[styles.actionSubtitle, { color: textColor }]}>
                  {action.subtitle}
                </Text>
              </View>
              
              <IconSymbol 
                name="chevron.right" 
                size={20} 
                color={useThemeColor({ light: '#9CA3AF', dark: '#6B7280' }, 'text')} 
              />
            </TouchableOpacity>
          ))}
        </View>

        {/* Section défis récents */}
        <View style={styles.recentSection}>
          <View style={styles.sectionHeader}>
            <ThemedText type="subtitle" style={styles.sectionTitle}>
              Activité récente
            </ThemedText>
            <TouchableOpacity onPress={() => router.push('/challenges')}>
              <Text style={styles.seeAllText}>Voir tout</Text>
            </TouchableOpacity>
          </View>
          
          <View style={[styles.emptyState, { backgroundColor: cardColor }]}>
            <IconSymbol name="target" size={48} color="#9CA3AF" />
            <ThemedText style={styles.emptyStateText}>
              Aucune activité récente
            </ThemedText>
            <ThemedText style={[styles.emptyStateSubtext, { color: textColor }]}>
              Créez votre premier défi pour commencer !
            </ThemedText>
          </View>
        </View>

        {/* Section premium (si pas premium) */}
        {!isPremium && (
          <View style={styles.premiumSection}>
            <View style={[styles.premiumCard, { backgroundColor: '#FF4B8B' }]}>
              <View style={styles.premiumContent}>
                <Text style={styles.premiumTitle}>✨ Passez Premium</Text>
                <Text style={styles.premiumDescription}>
                  Débloquez les défis multi-étapes, plus de stockage et bien plus !
                </Text>
                <Button
                  title="Découvrir Premium"
                  variant="secondary"
                  size="small"
                  onPress={() => router.push('/premium')}
                  style={styles.premiumButton}
                />
              </View>
            </View>
          </View>
        )}

        {/* Debug en développement */}
        {__DEV__ && (
          <View style={styles.debugSection}>
            <ThemedText type="subtitle">Debug (Dev only)</ThemedText>
            <Button
              title="Déconnexion"
              variant="outline"
              size="small"
              onPress={handleLogout}
              style={styles.debugButton}
            />
          </View>
        )}
        
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
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 24,
  },
  greeting: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#FF4B8B',
  },
  subtitle: {
    fontSize: 16,
    marginTop: 4,
    opacity: 0.8,
  },
  premiumBadge: {
    backgroundColor: '#FFD700',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  premiumText: {
    color: '#161D3F',
    fontSize: 12,
    fontWeight: '600',
  },
  statsContainer: {
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
  statItem: {
    alignItems: 'center',
  },
  statValue: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#FF4B8B',
  },
  statLabel: {
    fontSize: 14,
    marginTop: 4,
    opacity: 0.7,
  },
  actionsSection: {
    marginBottom: 24,
  },
  actionCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
  },
  actionIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 16,
  },
  actionContent: {
    flex: 1,
  },
  actionTitle: {
    fontSize: 16,
    fontWeight: '600',
  },
  actionSubtitle: {
    fontSize: 14,
    opacity: 0.7,
    marginTop: 2,
  },
  recentSection: {
    marginBottom: 24,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  seeAllText: {
    color: '#FF4B8B',
    fontSize: 14,
    fontWeight: '500',
  },
  emptyState: {
    alignItems: 'center',
    padding: 32,
    borderRadius: 12,
  },
  emptyStateText: {
    fontSize: 16,
    fontWeight: '500',
    marginTop: 12,
  },
  emptyStateSubtext: {
    fontSize: 14,
    opacity: 0.7,
    marginTop: 4,
    textAlign: 'center',
  },
  premiumSection: {
    marginBottom: 24,
  },
  premiumCard: {
    borderRadius: 16,
    padding: 20,
  },
  premiumContent: {
    alignItems: 'center',
  },
  premiumTitle: {
    color: 'white',
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 8,
  },
  premiumDescription: {
    color: 'white',
    fontSize: 14,
    textAlign: 'center',
    opacity: 0.9,
    marginBottom: 16,
  },
  premiumButton: {
    backgroundColor: 'white',
  },
  debugSection: {
    marginTop: 24,
    padding: 16,
    backgroundColor: '#FEE2E2',
    borderRadius: 8,
  },
  debugButton: {
    marginTop: 8,
  },
});