<script setup>
import { ref } from 'vue';
import { usePollStore } from '@/stores/usePollStore';

const emit = defineEmits(['edit']);
const { polls, deletePoll, startPoll } = usePollStore();

const linkCopied = ref(null);
const deleting = ref(null);

function pollStatus(poll) {
  if (poll.is_draft) return { label: 'Brouillon', cls: 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400' };
  if (poll.ends_at && new Date(poll.ends_at) < new Date())
    return { label: 'Terminé', cls: 'bg-rose-100 text-rose-700' };
  return { label: 'En cours', cls: 'bg-teal-100 text-teal-700' };
}

function fmt(dateStr) {
  if (!dateStr) return null;
  return new Date(dateStr).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

function fmtDuration(s) {
  if (!s) return null;
  if (s < 3600) return `${Math.round(s / 60)} min`;
  if (s < 86400) return `${(s / 3600).toFixed(1).replace('.0', '')} h`;
  return `${Math.round(s / 86400)} j`;
}

function voteUrl(poll) {
  return window.location.origin + '/polls/' + poll.secret_token;
}

function copyLink(poll) {
  navigator.clipboard.writeText(voteUrl(poll)).then(() => {
    linkCopied.value = poll.id;
    setTimeout(() => { linkCopied.value = null; }, 2000);
  });
}

async function handleStart(poll) {
  try {
    await startPoll(poll.id);
  } catch (err) {
    alert(err?.data?.message || 'Erreur lors du démarrage.');
  }
}

async function handleDelete(poll) {
  if (!confirm(`Supprimer « ${poll.title || poll.question} » ?`)) return;
  deleting.value = poll.id;
  try {
    await deletePoll(poll.id);
  } catch (err) {
    alert(err?.data?.message || 'Erreur lors de la suppression.');
  } finally {
    deleting.value = null;
  }
}
</script>

<template>
  <div>
    <p v-if="polls.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-12">
      Aucun sondage. Créez-en un via le bouton « + Nouveau » !
    </p>

    <div class="space-y-6">
      <div
        v-for="poll in polls"
        :key="poll.id"
        class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6"
      >
        <!-- Titre + badge -->
        <div class="flex items-start justify-between gap-3 mb-2">
          <div class="flex-1 min-w-0">
            <h3 class="font-bold text-gray-900 dark:text-white truncate">
              {{ poll.title || poll.question }}
            </h3>
            <p v-if="poll.title" class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ poll.question }}</p>
          </div>
          <span
            class="text-xs font-medium px-2 py-0.5 rounded-full shrink-0"
            :class="pollStatus(poll).cls"
          >
            {{ pollStatus(poll).label }}
          </span>
        </div>

        <!-- Méta -->
        <div class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5 mb-4">
          <p v-if="poll.started_at">Démarré : {{ fmt(poll.started_at) }}</p>
          <p v-if="poll.ends_at">Fin : {{ fmt(poll.ends_at) }}</p>
          <p v-else-if="poll.duration">Durée : {{ fmtDuration(poll.duration) }}</p>
          <p>{{ poll.options?.length ?? 0 }} option{{ (poll.options?.length ?? 0) !== 1 ? 's' : '' }}</p>
          <p v-if="!poll.results_public" class="text-amber-500">Résultats privés</p>
          <p v-if="poll.allow_multiple_choices" class="text-blue-500">Choix multiples</p>
        </div>

        <!-- Lien de partage (hors brouillon) -->
        <div
          v-if="!poll.is_draft"
          class="flex items-center gap-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-gray-600 rounded-md px-3 py-2 mb-4 text-xs"
        >
          <span class="text-gray-500 dark:text-gray-400 truncate flex-1">{{ voteUrl(poll) }}</span>
          <button
            @click="copyLink(poll)"
            class="text-teal-600 dark:text-teal-400 font-medium hover:text-teal-800 dark:hover:text-teal-300 shrink-0"
          >
            {{ linkCopied === poll.id ? '✓ Copié' : 'Copier' }}
          </button>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
          <button
            v-if="poll.is_draft"
            @click="handleStart(poll)"
            class="text-sm px-4 py-2 bg-teal-600 dark:bg-purple-900 text-white rounded-md hover:bg-teal-700 dark:hover:bg-purple-800 transition"
          >
            Démarrer
          </button>

          <a
            v-if="!poll.is_draft"
            :href="voteUrl(poll)"
            target="_blank"
            class="text-sm px-4 py-2 bg-teal-600 dark:bg-purple-900 text-white rounded-md hover:bg-teal-700 dark:hover:bg-purple-800 transition"
          >
            Voir le sondage
          </a>

          <button
            @click="emit('edit', poll)"
            class="text-sm px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-slate-600 transition"
          >
            Modifier
          </button>

          <button
            @click="handleDelete(poll)"
            :disabled="deleting === poll.id"
            class="text-sm px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 rounded-md hover:bg-red-100 transition disabled:opacity-50"
          >
            {{ deleting === poll.id ? '...' : 'Supprimer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
