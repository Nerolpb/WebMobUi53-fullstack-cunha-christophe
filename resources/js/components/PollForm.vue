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
  <article class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6">
    <header class="mb-6">
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">
        {{ isEdit ? 'Modifier le sondage' : 'Nouveau sondage' }}
      </h2>
    </header>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Titre -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Titre <span class="text-gray-400 dark:text-gray-500">(optionnel)</span>
        </label>
        <input
          v-model="title"
          type="text"
          placeholder="Titre du sondage"
          class="w-full px-3 py-2 border rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-teal-500 dark:focus:ring-purple-500"
        />
      </div>

      <!-- Question -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Question <span class="text-red-500">*</span>
        </label>
        <input
          v-model="question"
          type="text"
          required
          placeholder="Quelle est votre question ?"
          class="w-full px-3 py-2 border rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-teal-500 dark:focus:ring-purple-500"
        />
      </div>

      <!-- Options -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Options <span class="text-red-500">*</span>
        </label>
        <div class="space-y-2">
          <div v-for="(opt, i) in options" :key="i" class="flex gap-2 items-center">
            <input
              v-model="opt.label"
              type="text"
              required
              :placeholder="`Option ${i + 1}`"
              class="flex-1 px-3 py-2 border rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-teal-500 dark:focus:ring-purple-500"
            />
            <button
              type="button"
              @click="removeOption(i)"
              :disabled="options.length <= 2"
              class="text-red-400 hover:text-red-600 disabled:opacity-25 text-lg leading-none px-1"
              title="Supprimer"
            >✕</button>
          </div>
        </div>
        <button
          type="button"
          @click="addOption"
          class="mt-2 text-sm text-teal-600 dark:text-teal-400 hover:text-teal-800 dark:hover:text-teal-300 font-medium"
        >
          + Ajouter une option
        </button>
      </div>

      <!-- Paramètres -->
      <fieldset class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3">
        <legend class="text-sm font-semibold text-gray-700 dark:text-gray-300 px-1">Paramètres</legend>

        <label class="flex items-center gap-3 cursor-pointer select-none">
          <input type="checkbox" v-model="allowMultiple" class="accent-teal-600 w-4 h-4" />
          <span class="text-sm text-gray-700 dark:text-gray-300">Autoriser plusieurs réponses</span>
        </label>

        <label class="flex items-center gap-3 cursor-pointer select-none">
          <input type="checkbox" v-model="resultsPublic" class="accent-teal-600 w-4 h-4" />
          <span class="text-sm text-gray-700 dark:text-gray-300">Résultats publics (visibles sans connexion)</span>
        </label>

        <label class="flex items-center gap-3 cursor-pointer select-none">
          <input type="checkbox" v-model="allowVoteChange" class="accent-teal-600 w-4 h-4" />
          <span class="text-sm text-gray-700 dark:text-gray-300">Autoriser la modification du vote</span>
        </label>

        <div>
          <label class="block text-sm text-gray-700 dark:text-gray-300 mb-1">
            Durée <span class="text-gray-400 dark:text-gray-500">(en minutes — vide = illimitée)</span>
          </label>
          <input
            v-model="durationMins"
            type="number"
            min="1"
            placeholder="ex: 60"
            class="w-28 px-3 py-2 border rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-teal-500 dark:focus:ring-purple-500 text-sm"
          />
        </div>
      </fieldset>

      <!-- Démarrer maintenant (création seulement) -->
      <label v-if="!isEdit" class="flex items-center gap-3 cursor-pointer select-none">
        <input type="checkbox" v-model="startNow" class="accent-teal-600 w-4 h-4" />
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Démarrer immédiatement après la création</span>
      </label>

      <!-- Erreur -->
      <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-md p-3 text-sm">
        {{ error }}
      </div>

      <!-- Boutons -->
      <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
        <button
          type="submit"
          :disabled="submitting"
          class="flex-1 px-4 py-2 bg-teal-600 dark:bg-purple-900 text-white rounded-md font-medium hover:bg-teal-700 dark:hover:bg-purple-800 disabled:opacity-50 transition"
        >
          {{ submitting ? 'Enregistrement...' : (isEdit ? 'Sauvegarder' : 'Créer') }}
        </button>
        <button
          type="button"
          @click="emit('cancel')"
          class="flex-1 px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-md font-medium hover:bg-gray-200 dark:hover:bg-slate-600 transition"
        >
          Annuler
        </button>
      </div>

    </form>
  </article>
</template>
