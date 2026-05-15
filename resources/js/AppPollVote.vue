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

// Sélections de vote
const selectedOption  = ref(null);   // choix unique (radio)
const selectedOptions = ref([]);     // choix multiple (checkboxes)

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

// Pré-sélection des votes existants (une seule fois au chargement initial)
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
  if (poll.value.is_draft)   return 'bg-slate-100 text-slate-600';
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
    <div v-if="loading" class="text-center py-12 text-slate-400">Chargement…</div>

    <!-- Erreur de chargement -->
    <div
      v-else-if="fetchError"
      class="bg-rose-50 border border-rose-200 rounded-xl p-6 text-rose-700 text-center"
    >
      Sondage introuvable ou une erreur est survenue.
    </div>

    <!-- Contenu du sondage -->
    <div v-else-if="poll" class="space-y-6">

      <!-- En-tête : titre + badge statut -->
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">
            {{ poll.title || poll.question }}
          </h1>
          <p v-if="poll.title" class="text-slate-600 mt-1">{{ poll.question }}</p>
          <p v-if="poll.ends_at" class="text-xs text-slate-400 mt-1">
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
        class="bg-teal-50 border border-teal-200 rounded-xl p-4"
      >
        <p class="text-xs font-semibold text-teal-700 mb-2 uppercase tracking-wide">
          Lien de partage
        </p>
        <div class="flex items-center gap-2">
          <code class="text-xs text-teal-800 break-all flex-1">{{ shareUrl }}</code>
          <button
            @click="copyShareLink"
            class="text-xs bg-teal-600 text-white px-3 py-1.5 rounded-lg hover:bg-teal-700 transition shrink-0"
          >
            {{ linkCopied ? '✓ Copié !' : 'Copier' }}
          </button>
        </div>
      </div>

      <!-- Bannière : brouillon -->
      <div
        v-if="poll.is_draft"
        class="bg-slate-100 border border-slate-200 rounded-xl p-4 text-slate-600"
      >
        Ce sondage n'est pas encore ouvert au vote.
      </div>

      <!-- Bannière : expiré -->
      <div
        v-else-if="poll.is_expired"
        class="bg-rose-50 border border-rose-200 rounded-xl p-4"
      >
        <p class="font-semibold text-rose-700">Ce sondage est terminé.</p>
        <p class="text-sm text-rose-600 mt-1">Il n'est plus possible de voter.</p>
      </div>

      <!-- Formulaire de vote -->
      <div v-else-if="canVote">
        <h2 class="text-lg font-semibold text-slate-900 mb-3">
          {{ hasVoted ? 'Modifier votre vote' : 'Votez' }}
          <span v-if="poll.allow_multiple_choices" class="text-sm font-normal text-slate-500">
            (plusieurs choix possibles)
          </span>
        </h2>

        <div v-if="voteSuccess && !hasVoted" class="bg-teal-50 border border-teal-200 rounded-xl p-4 mb-4 text-teal-700">
          Vote enregistré !
        </div>

        <form @submit.prevent="submitVote" class="space-y-2">
          <label
            v-for="opt in poll.options"
            :key="opt.id"
            class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition"
            :class="isSelected(opt.id)
              ? 'border-teal-400 bg-teal-50'
              : 'border-slate-200 hover:bg-slate-50'"
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
            <span class="text-slate-800">{{ opt.label }}</span>
          </label>

          <p v-if="voteError" class="text-rose-600 text-sm pt-1">{{ voteError }}</p>

          <button
            type="submit"
            :disabled="submitting"
            class="w-full mt-2 bg-teal-600 text-white py-3 rounded-xl font-semibold hover:bg-teal-700 disabled:opacity-50 transition"
          >
            {{ submitting ? 'Envoi en cours…' : (hasVoted ? 'Modifier le vote' : 'Voter') }}
          </button>
        </form>
      </div>

      <!-- Déjà voté, changement non autorisé -->
      <div
        v-else-if="hasVoted && !poll.is_draft && !poll.is_expired"
        class="bg-teal-50 border border-teal-200 rounded-xl p-4 text-teal-700"
      >
        Vous avez déjà voté à ce sondage.
      </div>

      <!-- Non connecté, sondage actif -->
      <div
        v-else-if="!isAuthenticated && !poll.is_draft && !poll.is_expired"
        class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-blue-700"
      >
        <a :href="loginUrl" class="font-semibold underline hover:text-blue-900">
          Connectez-vous
        </a>
        pour participer à ce sondage.
      </div>

      <!-- Résultats -->
      <div v-if="showResults">
        <h2 class="text-lg font-semibold text-slate-900 mb-3">
          Résultats
          <span class="text-xs font-normal text-slate-400 ml-1">(mis à jour automatiquement)</span>
        </h2>
        <PollChart
          :options="poll.options"
          :total-votes="poll.total_votes ?? 0"
          :user-votes="poll.user_votes ?? []"
        />
      </div>

      <!-- Résultats privés -->
      <div v-else-if="!showResults && !poll.is_draft" class="text-sm text-slate-400 italic">
        Les résultats ne sont pas publics.
      </div>

    </div>
  </div>
</template>
