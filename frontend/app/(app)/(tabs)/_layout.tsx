// app/(app)/(tabs)/_layout.tsx - SOLUTION GLOBALE
import { Tabs } from 'expo-router';
import React from 'react';
import { Platform } from 'react-native';

import { HapticTab } from '@/components/HapticTab';
import { IconSymbol } from '@/components/ui/IconSymbol';
import TabBarBackground from '@/components/ui/TabBarBackground';
import { useColorScheme } from '@/hooks/useColorScheme';

export default function TabLayout() {
  const colorScheme = useColorScheme();

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: '#FF4B8B',
        headerShown: false,
        tabBarButton: HapticTab,
        tabBarBackground: TabBarBackground,
        
        // 🔥 SOLUTION GLOBALE - Plus de position absolute qui fout la merde
        tabBarStyle: {
          backgroundColor: colorScheme === 'dark' ? '#151718' : '#fff',
          borderTopWidth: 0.5,
          borderTopColor: colorScheme === 'dark' ? '#374151' : 'rgba(0, 0, 0, 0.1)',
          height: Platform.OS === 'ios' ? 90 : 60, // Hauteur adaptée
          paddingBottom: Platform.OS === 'ios' ? 25 : 5, // Espace pour home indicator
          paddingTop: 5,
        },
        
        tabBarLabelStyle: {
          fontSize: 12,
          fontWeight: '500',
          marginBottom: Platform.OS === 'ios' ? 0 : 5,
        },
        
        tabBarIconStyle: {
          marginBottom: Platform.OS === 'ios' ? -3 : 0,
        },
      }}>
      
      <Tabs.Screen
        name="index"
        options={{
          title: 'Accueil',
          tabBarIcon: ({ color }) => <IconSymbol size={26} name="house.fill" color={color} />,
        }}
      />
      
      <Tabs.Screen
        name="challenges"
        options={{
          title: 'Défis',
          tabBarIcon: ({ color }) => <IconSymbol size={26} name="target" color={color} />,
        }}
      />
      
      <Tabs.Screen
        name="truth-or-dare"
        options={{
          title: 'Action/Vérité',
          tabBarIcon: ({ color }) => <IconSymbol size={26} name="dice" color={color} />,
        }}
      />
      
      <Tabs.Screen
        name="friends"
        options={{
          title: 'Amis',
          tabBarIcon: ({ color }) => <IconSymbol size={26} name="people" color={color} />,
        }}
      />
      
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Profil',
          tabBarIcon: ({ color }) => <IconSymbol size={26} name="person.circle" color={color} />,
        }}
      />
    </Tabs>
  );
}