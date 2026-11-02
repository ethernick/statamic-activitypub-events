import '../css/cp.css';
import SettingsEvents from './components/settings/SettingsEvents.vue';
import EventsDashboard from './components/event/EventsDashboard.vue';
import InboxNewEvent from './components/inbox/InboxNewEvent.vue';
import EventInboxModals from './components/inbox/EventInboxModals.vue';
import EventActionBar from './components/inbox/EventActionBar.vue';

const bootHooks = () => {
    if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
        Statamic.$activitypub.hooks.register('inbox-new-dropdown', {
            component: InboxNewEvent,
            priority: 14
        });

        Statamic.$activitypub.hooks.register('inbox-modals', {
            component: EventInboxModals,
            priority: 15
        });

        Statamic.$activitypub.hooks.register('inbox-secondary-actions', {
            component: EventActionBar,
            priority: 10
        });
    } else if (typeof Statamic !== 'undefined') {
        setTimeout(bootHooks, 10);
    }
};

const bootComponents = () => {
    if (typeof Statamic !== 'undefined') {
        Statamic.booting(() => {
            Statamic.$components.register('settings-events-tab', SettingsEvents);
            Statamic.$components.register('events-dashboard', EventsDashboard);
        });
    } else {
        setTimeout(bootComponents, 10);
    }
};

bootComponents();
bootHooks();
