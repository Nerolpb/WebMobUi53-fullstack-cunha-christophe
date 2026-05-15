import { ref } from 'vue';
import { useFetchApi } from '@/composables/useFetchApi';

const polls = ref([]);

export function usePollStore() {
  const { fetchApi } = useFetchApi();

  function setPolls(data) {
    polls.value = data;
  }

  async function createPoll(pollData) {
    const result = await fetchApi({ url: 'polls', method: 'POST', data: pollData });
    polls.value.unshift(result);
    return result;
  }

  async function updatePoll(id, pollData) {
    const result = await fetchApi({ url: 'polls/' + id, method: 'PUT', data: pollData });
    const idx = polls.value.findIndex(p => p.id === id);
    if (idx !== -1) polls.value[idx] = result;
    return result;
  }

  async function startPoll(id) {
    const result = await fetchApi({ url: 'polls/' + id + '/start', method: 'POST' });
    const idx = polls.value.findIndex(p => p.id === id);
    if (idx !== -1) polls.value[idx] = result;
    return result;
  }

  async function deletePoll(id) {
    await fetchApi({ url: 'polls/' + id, method: 'DELETE' });
    polls.value = polls.value.filter(p => p.id !== id);
  }

  return { polls, setPolls, createPoll, updatePoll, startPoll, deletePoll };
}
