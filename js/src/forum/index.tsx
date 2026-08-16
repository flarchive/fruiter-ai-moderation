import app from 'flarum/forum/app';

import ContentModeratedNotification from './components/ContentModeratedNotification';

app.initializers.add('fruiter-ai-moderation', () => {
  app.notificationComponents.contentModerated = ContentModeratedNotification;
});
