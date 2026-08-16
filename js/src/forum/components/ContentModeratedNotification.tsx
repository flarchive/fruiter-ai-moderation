import app from 'flarum/forum/app';
import Notification from 'flarum/components/Notification';

export default class ContentModeratedNotification extends Notification {
  icon() {
    return 'fas fa-robot';
  }

  href() {
    const post = this.attrs.notification.subject();
    const discussion = post.discussion();

    return app.route.discussion(discussion, post.number());
  }

  content() {
    const data = this.attrs.notification.content() || {};
    const category = data.category || '';
    const reason = data.reason || '';

    if (!category && !reason) {
      return app.translator.trans('fruiter-ai-moderation.forum.notifications.content_moderated_text');
    }

    const detail = [category, reason].filter(Boolean).join('：');
    return app.translator.trans('fruiter-ai-moderation.forum.notifications.content_moderated_detail', { detail });
  }
}
