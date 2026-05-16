<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useFetchApi } from '@/composables/useFetchApi';
import { usePolling } from '@/composables/usePolling';
import PollChart from './components/PollChart.vue';

const props = defineProps({
  token:           { type: String,  required: true },
  isAuthenticated: { type: Boolean, default: false },
  loginUrl:        { type: String,  default: '/auth/login' },
});

const { fetchApi } = useFetchApi();

const poll       = ref(null);
const loading    = ref(true);
const fetchError = ref(null);

const selectedOption  = ref(null);
const selectedOptions = ref([]);

const submitting   = ref(false);
const voteError    = ref(null);
const voteSuccess  = ref(false);
const initialized  = ref(false);
const linkCopied   = ref(false);

async function loadPoll() {
  try {
    poll.value = await fetchApi({ url: `polls/${props.token}` });
    fetchError.value = null;
  } catch (err) {
    fetchError.value = err;
  } finally {
    loading.value = false;
  }
}

watch(poll, (newPoll) => {
  if (newPoll && !initialized.value) {
    initialized.value = true;
    const uv = newPoll.user_votes ?? [];
    if (uv.length) {
      if (newPoll.allow_multiple_choices) {
        selectedOptions.value = [...uv];
      } else {
        selectedOption.value = uv[0] ?? null;
      }
    }
  }
});

const canVote = computed(() => {
  if (!props.isAuthenticated || !poll.value) return false;
  if (poll.value.is_draft || poll.value.is_expired)  return false;
  const hasVoted = (poll.value.user_votes?.length ?? 0) > 0;
  return !hasVoted || poll.value.allow_vote_change;
});

const hasVoted = computed(() => (poll.value?.user_votes?.length ?? 0) > 0);

const showResults = computed(() =>
  poll.value && (poll.value.results_public || poll.value.is_owner)
);

const statusLabel = computed(() => {
  if (!poll.value) return '';
  if (poll.value.is_draft)   return 'Pas encore ouvert';
  if (poll.value.is_expired) return 'Terminé';
  return 'En cours';
});

const statusCls = computed(() => {
  if (!poll.value) return '';
  if (poll.value.is_draft)   return 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400';
  if (poll.value.is_expired) return 'bg-rose-100 text-rose-700';
  return 'bg-teal-100 text-teal-700';
});

function isSelected(optId) {
  if (poll.value?.allow_multiple_choices) return selectedOptions.value.includes(optId);
  return selectedOption.value === optId;
}

async function submitVote() {
  voteError.value = null;
  submitting.value = true;

  const optionIds = poll.value.allow_multiple_choices
    ? selectedOptions.value
    : (selectedOption.value ? [selectedOption.value] : []);

  if (!optionIds.length) {
    voteError.value = 'Veuillez sélectionner au moins une option.';
    submitting.value = false;
    return;
  }

  try {
    await fetchApi({
      url:    `polls/${props.token}/vote`,
      method: 'POST',
      data:   { option_ids: optionIds },
    });
    voteSuccess.value = true;
    await loadPoll();
  } catch (err) {
    voteError.value = err?.data?.message || 'Une erreur est survenue.';
  } finally {
    submitting.value = false;
  }
}

const shareUrl = window.location.href;

function copyShareLink() {
  navigator.clipboard.writeText(shareUrl).then(() => {
    linkCopied.value = true;
    setTimeout(() => { linkCopied.value = false; }, 2000);
  });
}

onMounted(loadPoll);
usePolling(loadPoll, 5000);
</script>

<template>
  <div class="py-4">

    <!-- Chargement -->
    <div v-if="loading" class="text-center py-12 text-gray-400 dark:text-gray-500">Chargement…</div>

    <!-- Erreur de chargement -->
    <div
      v-else-if="fetchError"
      class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6 text-red-700 dark:text-red-400 text-center"
    >
      Sondage introuvable ou une erreur est survenue.
    </div>

    <!-- Contenu du sondage -->
    <div v-else-if="poll" class="space-y-6">

      <!-- En-tête : titre + badge statut -->
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            {{ poll.title || poll.question }}
          </h1>
          <p v-if="poll.title" class="text-gray-600 dark:text-gray-400 mt-1">{{ poll.question }}</p>
          <p v-if="poll.ends_at" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <span v-if="poll.is_expired">Terminé le </span>
            <span v-else>Se termine le </span>
            {{ new Date(poll.ends_at).toLocaleString('fr-FR') }}
          </p>
        </div>
        <span class="text-xs font-semibold px-3 py-1 rounded-full shrink-0" :class="statusCls">
          {{ statusLabel }}
        </span>
      </div>

      <!-- Lien de partage (propriétaire) -->
      <div
        v-if="poll.is_owner"
        class="bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800 rounded-lg p-4"
      >
        <p class="text-xs font-semibold text-teal-700 dark:text-teal-400 mb-2 uppercase tracking-wide">
          Lien de partage
        </p>
        <div class="flex items-center gap-2">
          <code class="text-xs text-teal-800 dark:text-teal-300 break-all flex-1">{{ shareUrl }}</code>
          <button
            @click="copyShareLink"
            class="text-sm px-4 py-2 bg-teal-600 dark:bg-purple-900 text-white rounded-md hover:bg-teal-700 dark:hover:bg-purple-800 transition shrink-0"
          >
            {{ linkCopied ? '✓ Copié !' : 'Copier' }}
          </button>
        </div>
      </div>

      <!-- Bannière : brouillon -->
      <div
        v-if="poll.is_draft"
        class="bg-gray-100 dark:bg-slate-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4 text-gray-600 dark:text-gray-400"
      >
        Ce sondage n'est pas encore ouvert au vote.
      </div>

      <!-- Bannière : expiré -->
      <div
        v-else-if="poll.is_expired"
        class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4"
      >
        <p class="font-semibold text-red-700 dark:text-red-400">Ce sondage est terminé.</p>
        <p class="text-sm text-red-600 dark:text-red-500 mt-1">Il n'est plus possible de voter.</p>
      </div>

      <!-- Formulaire de vote -->
      <div v-else-if="canVote">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
          {{ hasVoted ? 'Modifier votre vote' : 'Votez' }}
          <span v-if="poll.allow_multiple_choices" class="text-sm font-normal text-gray-500 dark:text-gray-400">
            (plusieurs choix possibles)
          </span>
        </h2>

        <div v-if="voteSuccess && !hasVoted" class="bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800 rounded-lg p-4 mb-4 text-teal-700 dark:text-teal-300">
          Vote enregistré !
        </div>

        <form @submit.prevent="submitVote" class="space-y-2">
          <label
            v-for="opt in poll.options"
            :key="opt.id"
            class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition"
            :class="isSelected(opt.id)
              ? 'border-teal-400 bg-teal-50 dark:bg-teal-900/20'
              : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-slate-700'"
          >
            <input
              v-if="poll.allow_multiple_choices"
              type="checkbox"
              :value="opt.id"
              v-model="selectedOptions"
              class="accent-teal-600 w-4 h-4"
            />
            <input
              v-else
              type="radio"
              name="poll-option"
              :value="opt.id"
              v-model="selectedOption"
              class="accent-teal-600 w-4 h-4"
            />
            <span class="text-gray-800 dark:text-white">{{ opt.label }}</span>
          </label>

          <p v-if="voteError" class="text-red-600 dark:text-red-400 text-sm pt-1">{{ voteError }}</p>

          <button
            type="submit"
            :disabled="submitting"
            class="w-full mt-2 px-4 py-2 bg-teal-600 dark:bg-purple-900 text-white rounded-md font-semibold hover:bg-teal-700 dark:hover:bg-purple-800 disabled:opacity-50 transition"
          >
            {{ submitting ? 'Envoi en cours…' : (hasVoted ? 'Modifier le vote' : 'Voter') }}
          </button>
        </form>
      </div>

      <!-- Déjà voté, changement non autorisé -->
      <div
        v-else-if="hasVoted && !poll.is_draft && !poll.is_expired"
        class="bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800 rounded-lg p-4 text-teal-700 dark:text-teal-300"
      >
        Vous avez déjà voté à ce sondage.
      </div>

      <!-- Non connecté, sondage actif -->
      <div
        v-else-if="!isAuthenticated && !poll.is_draft && !poll.is_expired"
        class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-blue-700 dark:text-blue-300"
      >
        <a :href="loginUrl" class="font-semibold underline hover:opacity-80">
          Connectez-vous
        </a>
        pour participer à ce sondage.
      </div>

      <!-- Résultats -->
      <div v-if="showResults">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
          Résultats
          <span class="text-xs font-normal text-gray-400 dark:text-gray-500 ml-1">(mis à jour automatiquement)</span>
        </h2>
        <PollChart
          :options="poll.options"
          :total-votes="poll.total_votes ?? 0"
          :user-votes="poll.user_votes ?? []"
        />
      </div>

      <!-- Résultats privés -->
      <div v-else-if="!showResults && !poll.is_draft" class="text-sm text-gray-500 dark:text-gray-400 italic">
        Les résultats ne sont pas publics.
      </div>

    </div>
  </div>
</template>
