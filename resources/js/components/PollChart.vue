<script setup>
const props = defineProps({
  options:    { type: Array,  required: true },
  totalVotes: { type: Number, default: 0 },
  userVotes:  { type: Array,  default: () => [] },
});

function pct(opt) {
  if (!props.totalVotes) return 0;
  return Math.round((opt.votes_count ?? 0) / props.totalVotes * 100);
}

function isUserVote(opt) {
  return props.userVotes.includes(opt.id);
}
</script>

<template>
  <div class="space-y-4">
    <div v-for="opt in options" :key="opt.id">
      <div class="flex justify-between items-baseline text-sm mb-1 gap-2">
        <span
          class="font-medium truncate"
          :class="isUserVote(opt) ? 'text-teal-700 dark:text-teal-400' : 'text-gray-800 dark:text-white'"
        >
          {{ opt.label }}
          <span v-if="isUserVote(opt)" class="text-xs text-teal-500 ml-1">✓</span>
        </span>
        <span class="text-gray-500 dark:text-gray-400 tabular-nums shrink-0">
          {{ opt.votes_count ?? 0 }} ({{ pct(opt) }}%)
        </span>
      </div>
      <div class="h-5 bg-gray-200 dark:bg-slate-700 rounded-full overflow-hidden">
        <div
          class="h-full rounded-full transition-all duration-500"
          :class="isUserVote(opt) ? 'bg-teal-600' : 'bg-teal-400'"
          :style="{ width: pct(opt) + '%' }"
        ></div>
      </div>
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400 pt-1 border-t border-gray-200 dark:border-gray-700">
      Total : <strong>{{ totalVotes }}</strong> vote{{ totalVotes !== 1 ? 's' : '' }}
    </p>
  </div>
</template>
