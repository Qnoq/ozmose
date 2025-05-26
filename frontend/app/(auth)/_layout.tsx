import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';

export default function AppLayout() {
  return (
    <>
      <Stack
        screenOptions={{
          headerShown: false,
          presentation: 'card',
          animation: 'slide_from_right',
        }}>
        <Stack.Screen 
          name="(tabs)" 
          options={{
            headerShown: false,
          }} 
        />
        <Stack.Screen 
          name="profile" 
          options={{
            title: 'Profil',
            headerShown: true,
          }} 
        />
        <Stack.Screen 
          name="challenge/[id]" 
          options={{
            title: 'Défi',
            headerShown: true,
          }} 
        />
        <Stack.Screen 
          name="truth-or-dare" 
          options={{
            title: 'Action ou Vérité',
            headerShown: true,
          }} 
        />
      </Stack>
      <StatusBar style="auto" />
    </>
  );
}