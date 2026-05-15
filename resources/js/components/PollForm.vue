<script setup>
import { ref, computed } from 'vue';
import { usePollStore } from '@/stores/usePollStore';

const props = defineProps({
  poll: { type: Object, default: null },
});
const emit = defineEmits(['done', 'cancel']);

const { createPoll, updatePoll } = usePollStore();

const isEdit = computed(() => props.poll !== null);

const title           = ref(props.poll?.title ?? '');
const question        = ref(props.poll?.question ?? '');
const options         = ref(
  props.poll?.options?.length
    ? props.poll.options.map(o => ({ id: o.id, label: o.label }))
    : [{ id: null, label: '' }, { id: null, label: '' }]
);
const allowMultiple   = ref(props.poll?.allow_multiple_choices ?? false);
const resultsPublic   = ref(props.poll?.results_public ?? false);
const allowVoteChange = ref(props.poll?.allow_vote_change ?? false);
const durationMins    = ref(props.poll?.duration ? Math.round(props.poll.duration / 60) : '');
const startNow        = ref(false);

const submitting = ref(false);
const error      = ref(null);

function addOption() {
  options.value.push({ id: null, label: '' });
}

function removeOption(i) {
  if (options.value.length > 2) options.value.splice(i, 1);
}

async function submit() {
  error.value = null;
  submitting.value = true;

  const base = {
    title:                  title.value || null,
    question:               question.value,
    allow_multiple_choices: allowMultiple.value,
    results_public:         resultsPublic.value,
    allow_vote_change:      allowVoteChange.value,
    duration:               durationMins.value ? parseInt(durationMins.value) * 60 : null,
  };

  try {
    if (isEdit.value) {
      await updatePoll(props.poll.id, { ...base, options: options.value });
    } else {
      await createPoll({
        ...base,
        options:   options.value.map(o => o.label),
        start_now: startNow.value,
      });
    }
    emit('done');
  } catch (err) {
    error.value = err?.data?.errors
      ? Object.values(err.data.errors).flat().join(' ')
      : (err?.data?.message || 'Une erreur est survenue.');
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="max-w-xl mx-auto">
    <h2 class="text-xl font-bold text-slate-900 mb-5">
      {{ isEdit ? 'Modifier le sondage' : 'Nouveau sondage' }}
    </h2>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Titre -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Titre <span class="text-slate-400">(optionnel)</span></label>
        <input
          v-model="title"
          type="text"
          placeholder="Titre du sondage"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500"
        />
      </div>

      <!-- Question -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Question <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="question"
          type="text"
          required
          placeholder="Quelle est votre question ?"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500"
        />
      </div>

      <!-- Options -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">
          Options <span class="text-rose-500">*</span>
        </label>
        <div class="space-y-2">
          <div v-for="(opt, i) in options" :key="i" class="flex gap-2 items-center">
            <input
              v-model="opt.label"
              type="text"
              required
              :placeholder="`Option ${i + 1}`"
              class="flex-1 border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500"
            />
            <button
              type="button"
              @click="removeOption(i)"
              :disabled="options.length <= 2"
              class="text-rose-400 hover:text-rose-600 disabled:opacity-25 text-lg leading-none px-1"
              title="Supprimer"
            >✕</button>
          </div>
        </div>
        <button
          type="button"
          @click="addOption"
          class="mt-2 text-sm text-teal-600 hover:text-teal-800 font-medium"
        >
          + Ajouter une option
        </button>
      </div>

      <!-- Paramètres -->
      <fieldset class="border border-slate-200 rounded-xl p-4 space-y-3">
        <legend class="text-sm font-semibold text-slate-700 px-1">Paramètres</legend>

        <label class="flex items-center gap-3 cursor-pointer select-none">
          <input type="checkbox" v-model="allowMultiple" class="accent-teal-600 w-4 h-4" />
          <span class="text-sm text-slate-700">Autoriser plusieurs réponses</span>
        </label>

        <label class="flex items-center gap-3 cursor-pointer select-none">
          <input type="checkbox" v-model="resultsPublic" class="accent-teal-600 w-4 h-4" />
          <span class="text-sm text-slate-700">Résultats publics (visibles sans connexion)</span>
        </label>

        <label class="flex items-center gap-3 cursor-pointer select-none">
          <input type="checkbox" v-model="allowVoteChange" class="accent-teal-600 w-4 h-4" />
          <span class="text-sm text-slate-700">Autoriser la modification du vote</span>
        </label>

        <div>
          <label class="block text-sm text-slate-700 mb-1">
            Durée <span class="text-slate-400">(en minutes — vide = illimitée)</span>
          </label>
          <input
            v-model="durationMins"
            type="number"
            min="1"
            placeholder="ex: 60"
            class="w-28 border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm"
          />
        </div>
      </fieldset>

      <!-- Démarrer maintenant (création seulement) -->
      <label v-if="!isEdit" class="flex items-center gap-3 cursor-pointer select-none">
        <input type="checkbox" v-model="startNow" class="accent-teal-600 w-4 h-4" />
        <span class="text-sm font-medium text-slate-700">Démarrer immédiatement après la création</span>
      </label>

      <!-- Erreur -->
      <div v-if="error" class="bg-rose-50 border border-rose-200 text-rose-700 rounded-lg p-3 text-sm">
        {{ error }}
      </div>

      <!-- Boutons -->
      <div class="flex gap-3 pt-1">
        <button
          type="submit"
          :disabled="submitting"
          class="flex-1 bg-teal-600 text-white py-2.5 rounded-lg font-medium hover:bg-teal-700 disabled:opacity-50 transition"
        >
          {{ submitting ? 'Enregistrement...' : (isEdit ? 'Sauvegarder' : 'Créer') }}
        </button>
        <button
          type="button"
          @click="emit('cancel')"
          class="flex-1 bg-slate-100 text-slate-700 py-2.5 rounded-lg font-medium hover:bg-slate-200 transition"
        >
          Annuler
        </button>
      </div>

    </form>
  </div>
</template>
