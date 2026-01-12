<template>
  <div class="penny-container">
    <!-- Floating Penny Button -->
    <div
      v-if="!isOpen"
      class="penny-fab"
      @click="openChat"
    >
      <div class="penny-avatar">
        <q-icon name="smart_toy" size="28px" />
      </div>
      <div v-if="hasUnread" class="unread-badge">1</div>
    </div>

    <!-- Chat Overlay -->
    <Transition name="slide-up">
      <div v-if="isOpen" class="chat-overlay">
        <!-- Header -->
        <div class="chat-header">
          <div class="penny-info">
            <div class="penny-icon">
              <q-icon name="smart_toy" size="24px" />
            </div>
            <div>
              <div class="penny-name">Penny</div>
              <div class="penny-status">
                {{ isConnected ? 'Connected to n8n' : 'Your Financial Co-Pilot' }}
              </div>
            </div>
          </div>
          <q-btn flat round dense icon="close" color="white" @click="closeChat" />
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="chat-messages">
          <div
            v-for="(msg, idx) in messages"
            :key="idx"
            class="message"
            :class="msg.sender"
          >
            <div v-if="msg.sender === 'penny'" class="penny-msg-avatar">
              <q-icon name="smart_toy" size="16px" />
            </div>
            <div class="message-bubble" v-html="formatMessage(msg.text)"></div>
            <div v-if="msg.action" class="message-action">
              <q-btn
                flat
                dense
                size="sm"
                :label="msg.action.label"
                :color="msg.action.color || 'cyan'"
                @click="executeAction(msg.action)"
              />
            </div>
          </div>

          <!-- Typing Indicator -->
          <div v-if="isTyping" class="message penny">
            <div class="penny-msg-avatar">
              <q-icon name="smart_toy" size="16px" />
            </div>
            <div class="message-bubble typing">
              <span></span><span></span><span></span>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div v-if="showQuickActions" class="quick-actions">
          <q-chip
            v-for="action in quickActions"
            :key="action.label"
            clickable
            outline
            color="cyan"
            text-color="white"
            size="sm"
            @click="sendQuickAction(action)"
          >
            {{ action.label }}
          </q-chip>
        </div>

        <!-- Input -->
        <div class="chat-input">
          <q-input
            v-model="inputText"
            :placeholder="isListening ? 'Listening...' : 'Talk to Penny...'"
            outlined
            dense
            dark
            class="input-field"
            :class="{ 'listening': isListening }"
            @keyup.enter="sendMessage"
          >
            <template v-slot:append>
              <q-btn
                v-if="speechSupported"
                flat
                round
                dense
                :icon="isListening ? 'mic' : 'mic_none'"
                :color="isListening ? 'red' : 'grey'"
                :class="{ 'pulse-mic': isListening }"
                @click="toggleSpeech"
              />
              <q-btn
                flat
                round
                dense
                icon="send"
                color="cyan"
                :disable="!inputText.trim() || isListening"
                @click="sendMessage"
              />
            </template>
          </q-input>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useRouter } from 'vue-router';
import { pennyApi, type PennyMemory, type N8nContext } from '@/services/api/penny.api';

// Props
const props = defineProps<{
  avatarData: {
    name: string;
    totalXP: number;
    quests: Array<{ id: string; name: string; xp: number; completed: boolean }>;
    inventory: Array<{ id: string; name: string; alias: string; current: number; target: number }>;
    attributes: Array<{ id: string; name: string; progress: number; unlocked: boolean }>;
    targetRealm: { location: string; cost: number };
    questStreak: number;
  };
  budgetData: { income: number; expenses: number; spent: number } | null;
  taxShield: { sub_account_health: { sub_account_balance: number; coverage_percent: number; status_label: string } } | null;
}>();

// Emits
const emit = defineEmits<{
  (e: 'addQuest', quest: { name: string; xp: number }): void;
  (e: 'addItem', item: { name: string; alias: string; target: number; current: number; icon: string }): void;
  (e: 'addTrait', trait: { name: string; icon: string; progress: number; unlocked: boolean }): void;
  (e: 'setDestination', dest: { location: string; cost: number }): void;
  (e: 'updateAvatar', data: { name?: string }): void;
}>();

// Router for navigation
const router = useRouter();

// ==================== STATE ====================
const isOpen = ref(false);
const hasUnread = ref(true);
const isTyping = ref(false);
const isConnected = ref(false);
const inputText = ref('');
const isListening = ref(false);
const speechSupported = ref(false);
const messagesContainer = ref<HTMLElement | null>(null);

// Database-backed memory
const pennyMemory = ref<PennyMemory | null>(null);
const n8nContext = ref<N8nContext | null>(null);
const missingFields = ref<string[]>([]);

interface Message {
  sender: 'penny' | 'user';
  text: string;
  action?: { type: string; label: string; color?: string; data?: unknown; route?: string };
}

const messages = ref<Message[]>([]);

// ==================== COMPUTED ====================

// Quick actions - contextual based on what's missing in database
const showQuickActions = computed(() => messages.value.length > 0 && !isTyping.value);
const quickActions = computed(() => {
  const actions = [];
  const missing = missingFields.value;

  if (missing.includes('quests') || !pennyMemory.value?.has_quests) {
    actions.push({ label: 'Add Quest', command: 'quest' });
  }
  if (missing.includes('inventory') || !pennyMemory.value?.has_inventory) {
    actions.push({ label: 'Add Item', command: 'item' });
  }
  if (missing.includes('boss_goal') || !pennyMemory.value?.boss_goal_name) {
    actions.push({ label: 'Set Boss Goal', command: 'boss' });
  }
  actions.push({ label: 'Weekly Review', command: 'weekly' });
  if (actions.length < 4) actions.push({ label: 'Progress', command: 'progress' });

  return actions.slice(0, 4);
});

// ==================== METHODS ====================

async function openChat() {
  isOpen.value = true;
  hasUnread.value = false;

  // Fetch memory from database on every open
  await fetchMemoryFromDatabase();

  // If no messages yet, get intro from n8n
  if (messages.value.length === 0) {
    await getIntroFromN8n();
  }
}

function closeChat() {
  isOpen.value = false;
}

/**
 * Fetch user's Penny memory from database
 * This gives Penny total awareness of the Human Planning Center state
 */
async function fetchMemoryFromDatabase() {
  try {
    const response = await pennyApi.getMemory();
    pennyMemory.value = response.data;
    n8nContext.value = response.context;
    missingFields.value = response.missing_fields;
    isConnected.value = true;

    // Sync local avatar data to database
    await syncAvatarToDatabase();
  } catch (error) {
    console.error('Failed to fetch Penny memory:', error);
    isConnected.value = false;
  }
}

/**
 * Sync local avatar data to database
 */
async function syncAvatarToDatabase() {
  try {
    await pennyApi.syncFromAvatar({
      name: props.avatarData.name,
      totalXP: props.avatarData.totalXP,
      questStreak: props.avatarData.questStreak,
      quests: props.avatarData.quests.map(q => ({
        id: q.id,
        name: q.name,
        xp: q.xp,
        completed: q.completed,
      })),
      inventory: props.avatarData.inventory.map(i => ({
        id: i.id,
        name: i.name,
        alias: i.alias,
        icon: 'savings',
        target: i.target,
        current: i.current,
      })),
      attributes: props.avatarData.attributes.map(a => ({
        id: a.id,
        name: a.name,
        icon: 'verified',
        progress: a.progress,
        unlocked: a.unlocked,
      })),
      targetRealm: props.avatarData.targetRealm,
      healthy: true,
      secure: true,
    });
  } catch (error) {
    console.error('Failed to sync avatar to database:', error);
  }
}

/**
 * Get intro message from n8n webhook
 * Uses no-repeat logic - won't ask for known data
 */
async function getIntroFromN8n() {
  isTyping.value = true;

  try {
    const response = await pennyApi.chat('Hello', undefined);
    await addPennyMessage(response.reply, response.action || undefined);
  } catch (error) {
    console.error('Failed to get intro from n8n:', error);
    // Fallback to local intro
    await addLocalIntro();
  } finally {
    isTyping.value = false;
  }
}

/**
 * Local fallback intro when n8n is unavailable
 */
async function addLocalIntro() {
  const name = pennyMemory.value?.display_name || 'Adventurer';
  const missing = missingFields.value;

  if (!pennyMemory.value?.has_met_penny) {
    await addPennyMessage(`${name}. Penny here. Let's get to work.`);

    if (missing.length > 0) {
      const prompts: Record<string, string> = {
        'quests': '**DO** quadrant is empty. What\'s your first daily quest?',
        'inventory': '**HAVE** quadrant needs items. What are you saving for?',
        'traits': '**BE** quadrant is blank. What trait are you building?',
        'boss_goal': '**LIVE** needs a destination. What\'s the boss goal?',
      };
      for (const field of missing) {
        if (prompts[field]) {
          await addPennyMessage(prompts[field]);
          break;
        }
      }
    }
  } else if (missing.length > 0) {
    const prompts: Record<string, string> = {
      'quests': `${name}, **DO** is empty. First quest?`,
      'inventory': `${name}, no items in **HAVE**. What's the target?`,
      'traits': `${name}, **BE** needs a trait. What are you becoming?`,
      'boss_goal': `${name}, **LIVE** has no boss. What's the mission?`,
    };
    for (const field of missing) {
      if (prompts[field]) {
        await addPennyMessage(prompts[field]);
        break;
      }
    }
  } else {
    await addPennyMessage(`${name}. Quadrants set. What's next?`);
  }
}

function formatMessage(text: string): string {
  return text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
}

async function addPennyMessage(text: string, action?: Message['action']) {
  if (!messages.value) messages.value = [];
  messages.value.push({ sender: 'penny', text, action });
  scrollToBottom();
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
  });
}

/**
 * Send message to n8n webhook
 */
async function sendMessage() {
  const text = inputText.value.trim();
  if (!text) return;

  messages.value.push({ sender: 'user', text });
  inputText.value = '';
  scrollToBottom();

  isTyping.value = true;

  try {
    const response = await pennyApi.chat(text);
    await addPennyMessage(response.reply, response.action || undefined);

    // Refresh memory if it was updated
    if (response.memory_updated) {
      await fetchMemoryFromDatabase();
    }
  } catch (error) {
    console.error('Chat error:', error);
    await addPennyMessage('Connection lost. Try again.');
  } finally {
    isTyping.value = false;
  }
}

/**
 * Send quick action to n8n webhook
 */
async function sendQuickAction(action: { label: string; command: string }) {
  messages.value.push({ sender: 'user', text: action.label });
  scrollToBottom();

  isTyping.value = true;

  try {
    const response = await pennyApi.chat(action.label, action.command);
    await addPennyMessage(response.reply, response.action || undefined);

    if (response.memory_updated) {
      await fetchMemoryFromDatabase();
    }
  } catch (error) {
    console.error('Quick action error:', error);
    await addPennyMessage('Connection lost. Try again.');
  } finally {
    isTyping.value = false;
  }
}

/**
 * Execute action from Penny's response
 */
async function executeAction(action: Message['action']) {
  if (!action) return;

  switch (action.type) {
    case 'addQuest': {
      const questData = action.data as { name: string; xp: number };
      try {
        await pennyApi.addQuest(questData.name, questData.xp);
        emit('addQuest', questData);
        await addPennyMessage(`**${questData.name}** added. +${questData.xp}XP per completion.`);
        await fetchMemoryFromDatabase();
      } catch (error) {
        console.error('Failed to add quest:', error);
      }
      break;
    }

    case 'addItem': {
      const itemData = action.data as { name: string; alias: string; target: number; icon: string };
      try {
        await pennyApi.addItem(itemData.name, itemData.alias, itemData.target, 0, itemData.icon);
        emit('addItem', { ...itemData, current: 0 });
        await addPennyMessage(`**${itemData.alias}** added. R${itemData.target.toLocaleString()} target.`);
        await fetchMemoryFromDatabase();
      } catch (error) {
        console.error('Failed to add item:', error);
      }
      break;
    }

    case 'addTrait': {
      const traitData = action.data as { name: string; icon: string; progress: number };
      emit('addTrait', { ...traitData, unlocked: false });
      await addPennyMessage(`**${traitData.name}** trait added.`);
      break;
    }

    case 'addDestination': {
      const destData = action.data as { location: string; cost: number };
      try {
        await pennyApi.setBossGoal(destData.location, destData.cost);
        emit('setDestination', destData);
        await addPennyMessage(`**${destData.location}** set. R${destData.cost.toLocaleString()} target.`);
        await fetchMemoryFromDatabase();
      } catch (error) {
        console.error('Failed to set destination:', error);
      }
      break;
    }

    case 'navigate': {
      const route = action.route as string;
      if (route) {
        closeChat();
        router.push(route);
      }
      break;
    }
  }
}

// Speech Recognition setup
let recognition: SpeechRecognition | null = null;

function setupSpeechRecognition() {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (SpeechRecognition) {
    speechSupported.value = true;
    recognition = new SpeechRecognition();
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.lang = 'en-ZA'; // South African English

    recognition.onresult = (event: SpeechRecognitionEvent) => {
      const transcript = event.results[0][0].transcript;
      inputText.value = transcript;
      isListening.value = false;
      // Auto-send after speech
      sendMessage();
    };

    recognition.onerror = () => {
      isListening.value = false;
    };

    recognition.onend = () => {
      isListening.value = false;
    };
  }
}

function toggleSpeech() {
  if (!recognition) return;

  if (isListening.value) {
    recognition.stop();
    isListening.value = false;
  } else {
    recognition.start();
    isListening.value = true;
  }
}

// Check for Sunday briefing on mount
onMounted(() => {
  setupSpeechRecognition();

  const today = new Date();
  const lastBriefing = localStorage.getItem('penny_last_briefing');
  const todayStr = today.toDateString();

  if (today.getDay() === 0 && lastBriefing !== todayStr) {
    hasUnread.value = true;
  }
});

// Watch for messages to auto-scroll
watch(messages, () => {
  scrollToBottom();
}, { deep: true });
</script>

<style scoped>
.penny-container {
  position: fixed;
  bottom: 72px; /* Above bottom nav */
  right: 16px;
  z-index: 1000;
}

/* Floating Button */
.penny-fab {
  position: relative;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, #00838F, #00ACC1);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 20px rgba(0, 172, 193, 0.4);
  transition: all 0.3s ease;
}

.penny-fab:hover {
  transform: scale(1.05);
}

.penny-fab:active {
  transform: scale(0.95);
}

.penny-avatar {
  color: white;
}

.unread-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  width: 20px;
  height: 20px;
  background: #f44336;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  color: white;
  border: 2px solid #1a1a2e;
}

/* Chat Overlay */
.chat-overlay {
  position: fixed;
  bottom: 56px; /* Above the bottom nav bar */
  left: 0;
  right: 0;
  height: 65vh;
  max-height: 550px;
  background: #1a1a2e;
  border-radius: 20px 20px 0 0;
  display: flex;
  flex-direction: column;
  box-shadow: 0 -4px 30px rgba(0, 0, 0, 0.5);
}

/* Header */
.chat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  background: linear-gradient(135deg, #00838F, #006064);
  border-radius: 20px 20px 0 0;
}

.penny-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.penny-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.penny-name {
  font-size: 16px;
  font-weight: 700;
  color: white;
}

.penny-status {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.7);
}

/* Messages */
.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.message {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  max-width: 85%;
}

.message.user {
  align-self: flex-end;
  flex-direction: row-reverse;
}

.message.penny {
  align-self: flex-start;
}

.penny-msg-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: linear-gradient(135deg, #00838F, #00ACC1);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
}

.message-bubble {
  padding: 10px 14px;
  border-radius: 16px;
  font-size: 14px;
  line-height: 1.4;
  white-space: pre-line;
}

.message.penny .message-bubble {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  border-bottom-left-radius: 4px;
}

.message.user .message-bubble {
  background: linear-gradient(135deg, #00838F, #00ACC1);
  color: white;
  border-bottom-right-radius: 4px;
}

.message-action {
  margin-top: 8px;
}

/* Typing Indicator */
.typing {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 12px 16px;
}

.typing span {
  width: 8px;
  height: 8px;
  background: rgba(255, 255, 255, 0.4);
  border-radius: 50%;
  animation: typing 1.4s infinite;
}

.typing span:nth-child(2) { animation-delay: 0.2s; }
.typing span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
  0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
  30% { transform: translateY(-4px); opacity: 1; }
}

/* Quick Actions */
.quick-actions {
  padding: 8px 16px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Input */
.chat-input {
  padding: 12px 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  background: #1a1a2e;
}

.input-field {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 24px;
}

.input-field :deep(.q-field__control) {
  background: rgba(255, 255, 255, 0.08);
  border-radius: 24px;
}

.input-field :deep(.q-field__native) {
  color: white;
}

.input-field :deep(.q-field__native::placeholder) {
  color: rgba(255, 255, 255, 0.5);
}

/* Transitions */
.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.3s ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
  opacity: 0;
}

/* Listening state */
.input-field.listening :deep(.q-field__control) {
  border-color: #f44336;
  box-shadow: 0 0 10px rgba(244, 67, 54, 0.3);
}

/* Pulsing microphone */
.pulse-mic {
  animation: pulse-red 1.5s infinite;
}

@keyframes pulse-red {
  0% { transform: scale(1); }
  50% { transform: scale(1.2); }
  100% { transform: scale(1); }
}
</style>
