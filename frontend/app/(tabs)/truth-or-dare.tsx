import React from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/ThemedText';
import { IconSymbol } from '@/components/ui/IconSymbol';
import { useThemeColor } from '@/hooks/useThemeColor';

export default function TruthOrDareScreen() {
  const backgroundColor = useThemeColor({}, 'background');
  const textColor = useThemeColor({}, 'text');
  const cardColor = useThemeColor({ light: '#F8F9FA', dark: '#1F2937' }, 'background');

  return (
    <SafeAreaView style={[styles.container, { backgroundColor }]}>
      <ScrollView contentContainerStyle={styles.scrollContent}>
        
        <View style={styles.header}>
          <ThemedText type="title">Action ou Vérité</ThemedText>
          <ThemedText style={[styles.subtitle, { color: textColor }]}>
            Jouez entre amis avec vos questions personnalisées
          </ThemedText>
        </View>

        <View style={[styles.emptyState, { backgroundColor: cardColor }]}>
          <IconSymbol name="dice" size={64} color="#9CA3AF" />
          <ThemedText style={styles.emptyStateText}>
            Jeu en développement
          </ThemedText>
          <ThemedText style={[styles.emptyStateSubtext, { color: textColor }]}>
            Le jeu Action ou Vérité sera disponible une fois l'API connectée
          </ThemedText>
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
  header: {
    marginBottom: 24,
  },
  subtitle: {
    fontSize: 16,
    marginTop: 8,
    opacity: 0.7,
  },
  emptyState: {
    alignItems: 'center',
    padding: 48,
    borderRadius: 16,
    marginTop: 32,
  },
  emptyStateText: {
    fontSize: 18,
    fontWeight: '500',
    marginTop: 16,
  },
  emptyStateSubtext: {
    fontSize: 14,
    opacity: 0.7,
    marginTop: 8,
    textAlign: 'center',
  },
});