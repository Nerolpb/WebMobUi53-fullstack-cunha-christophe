<script setup>
import { ref } from 'vue';
import PollTable from './components/PollTable.vue';
import PollForm from './components/PollForm.vue';
import { usePollStore } from '@/stores/usePollStore';

const props = defineProps({
  polls:    { type: Array,  default: () => [] },
  loginUrl: { type: String, default: null },
  userId:   { type: Number, default: null },
  username: { type: String, default: null },
});

const { setPolls } = usePollStore();
setPolls(props.polls);

const view        = ref('list');
const editingPoll = ref(null);

function showCreate() {
  editingPoll.value = null;
  view.value = 'form';
}

function showEdit(poll) {
  editingPoll.value = poll;
  view.value = 'form';
}

function backToList() {
  editingPoll.value = null;
  view.value = 'list';
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-900">

    <!-- En-tête -->
    <header class="bg-teal-600 dark:bg-slate-800 text-white px-4 py-4 shadow">
      <div class="max-w-2xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-3">
          <a href="/" class="text-white hover:opacity-80 text-sm transition">← Accueil</a>
          <span class="opacity-40">|</span>
          <h1 class="font-bold">Mes sondages</h1>
          <span v-if="username" class="text-teal-200 dark:text-slate-400 text-sm">({{ username }})</span>
        </div>
        <button
          v-if="view === 'list'"
          @click="showCreate"
          class="bg-white text-teal-700 px-4 py-1.5 rounded-md font-semibold text-sm hover:bg-teal-50 transition"
        >
          + Nouveau
        </button>
      </div>
    </header>

    <!-- Contenu principal -->
    <main class="max-w-2xl mx-auto px-4 py-8">
      <PollTable
        v-if="view === 'list'"
        @edit="showEdit"
      />
      <PollForm
        v-else
        :poll="editingPoll"
        @done="backToList"
        @cancel="backToList"
      />
    </main>

  </div>
</template>
