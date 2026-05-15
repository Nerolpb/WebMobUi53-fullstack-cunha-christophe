import './bootstrap';
import { createApp } from 'vue';
import App from './AppPollVote.vue';

const el = document.getElementById('poll-vote-app');
const props = JSON.parse(el.dataset.props ?? '{}');

createApp(App, props).mount(el);
