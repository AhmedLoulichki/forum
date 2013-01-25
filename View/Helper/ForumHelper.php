<?php
/**
 * @copyright	Copyright 2006-2013, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/forum
 */

App::uses('AccessLevel', 'Forum.Model');

class ForumHelper extends AppHelper {

	/**
	 * Helpers.
	 *
	 * @var array
	 */
	public $helpers = array('Html', 'Session', 'Utility.Decoda');

	/**
	 * Modify Decoda before rendering the view.
	 *
	 * @param string $viewFile
	 */
	public function beforeRender($viewFile) {
		$censored = Configure::read('Forum.settings.censoredWords');

		if (is_string($censored)) {
			$censored = array_map('trim', explode(',', $censored));
		}

		$decoda = $this->Decoda->getDecoda();
		$decoda->addFilter(new \Decoda\Filter\BlockFilter(array(
			'spoilerToggle' => "$('spoiler-content-{id}').toggle();"
		)));

		if ($censored) {
			$decoda->getHook('Censor')->blacklist($censored);
		}
	}

	/**
	 * Output a users avatar.
	 *
	 * @param array $user
	 * @param int $size
	 * @return string
	 */
	public function avatar($user, $size = 100) {
		$userMap = Configure::read('Forum.userMap');
		$avatar = null;

		if (!empty($userMap['avatar']) && !empty($user['User'][$userMap['avatar']])) {
			$avatar = $this->Html->image($user['User'][$userMap['avatar']], array('width' => $size, 'height' => $size));

		} else if (Configure::read('Forum.settings.enableGravatar')) {
			$avatar = $this->gravatar($user['User'][$userMap['email']], array('size' => $size));
		}

		if ($avatar) {
			return $this->Html->div('avatar', $avatar);
		}

		return $avatar;
	}

	/**
	 * Determine the forum icon state.
	 *
	 * @param array $forum
	 * @return string
	 */
	public function forumIcon($forum) {
		$icon = 'open';

		if (isset($forum['LastPost']['created'])) {
			$lastPost = $forum['LastPost']['created'];
		} else if (isset($forum['LastTopic']['created'])) {
			$lastPost = $forum['LastTopic']['created'];
		}

		if ($forum['status'] == 0) {
			$icon = 'closed';
		} else if (isset($lastPost) && $lastPost > $this->Session->read('Forum.lastVisit')) {
			$icon = 'new';
		}

		return $this->Html->image('/forum/img/forum_' . $icon . '.png', array(
			'alt' => ucfirst($icon)
		));
	}

	/**
	 * Gets the highest access level.
	 *
	 * @return int
	 */
	public function getAccess() {
		return $this->Session->read('Forum.access');
	}

	/**
	 * Get topics made in the past hour.
	 *
	 * @return int
	 */
	public function getTopicsMade() {
		$pastHour = strtotime('-1 hour');
		$count = 0;

		if ($topics = $this->Session->read('Forum.topics')) {
			foreach ($topics as $time) {
				if ($time >= $pastHour) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Get posts made in the past hour.
	 *
	 * @return int
	 */
	public function getPostsMade() {
		$pastHour = strtotime('-1 hour');
		$count = 0;

		if ($posts = $this->Session->read('Forum.posts')) {
			foreach ($posts as $time) {
				if ($time >= $pastHour) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Render out a gravatar thumbnail based on an email.
	 *
	 * @param string $email
	 * @param array $options
	 * @param array $attributes
	 * @return string
	 */
	public function gravatar($email, array $options = array(), array $attributes = array()) {
		$options = $options + array(
			'default' => 'mm',
			'size' => 80,
			'rating' => 'g',
			'hash' => 'md5',
			'secure' => env('HTTPS')
		);

		$email = Security::hash(strtolower(trim($email)), $options['hash']);
		$query = array();

		if ($options['secure']) {
			$image = 'https://secure.gravatar.com/avatar/' . $email;
		} else {
			$image = 'http://www.gravatar.com/avatar/' . $email;
		}

		foreach (array('default' => 'd', 'size' => 's', 'rating' => 'r') as $key => $param) {
			$query[] = $param . '=' . urlencode($options[$key]);
		}

		$image .= '?' . implode('&amp;', $query);

		return $this->Html->image($image, $attributes);
	}

	/**
	 * Checks to see if the user has mod status.
	 *
	 * @param int $level
	 * @param int $forum_id
	 * @return bool
	 */
	public function hasAccess($level = AccessLevel::MEMBER, $forum_id = null) {
		if ($this->Session->read('Forum.isAdmin')) {
			return true;

		} else if ($level <= AccessLevel::SUPER && $this->Session->read('Forum.isSuper')) {
			return true;

		} else if ($level <= AccessLevel::MOD && $forum_id) {
			return in_array($forum_id, $this->Session->read('Forum.moderates'));
		}

		return ($this->getAccess() >= $level);
	}

	/**
	 * Output the highest access level.
	 *
	 * @param array $levels
	 * @return string
	 */
	public function highestAccessLevel($levels) {
		$highest = array();

		foreach ($levels as $level) {
			if (!$highest) {
				$highest = $level;
			} else if ($level['AccessLevel']['level'] > $highest['AccessLevel']['level']) {
				$highest = $level;
			}
		}

		return $highest['AccessLevel']['title'];
	}

	/**
	 * Prebuilt option lists for form selects.
	 *
	 * @param string $type
	 * @param string $value
	 * @param bool $guest
	 * @return array|string
	 */
	public function options($type = 'status', $value = '', $guest = false) {
		if ($type === 'status') {
			$options = array(
				1 => __d('forum', 'Yes'),
				0 => __d('forum', 'No')
			);

		} else if ($type === 'topicStatus') {
			$options = array(
				1 => __d('forum', 'Open'),
				0 => __d('forum', 'Closed')
			);

		} else if ($type === 'forumStatus') {
			$options = array(
				1 => __d('forum', 'Visible'),
				0 => __d('forum', 'Hidden')
			);

		} else if ($type === 'access') {
			$options = array(
				1 => '1 (' . __d('forum', 'Member') . ')',
				2 => '2',
				3 => '3',
				4 => '4 (' . __d('forum', 'Moderator') . ')',
				5 => '5',
				6 => '6',
				7 => '7 (' . __d('forum', 'Super Moderator') . ')',
				8 => '8',
				9 => '9',
				10 => '10 (' . __d('forum', 'Administrator') . ')'
			);

			if ($guest) {
				array_unshift($options, '0 (' . __d('forum', 'Guest') . ')');
			}

		} else if ($type === 'userStatus') {
			$options = array(
				0 => __d('forum', 'Active'),
				1 => __d('forum', 'Banned')
			);

		} else if ($type === 'topicTypes') {
			$options = array(
				0 => __d('forum', 'Normal'),
				1 => __d('forum', 'Sticky'),
				2 => __d('forum', 'Important'),
				3 => __d('forum', 'Announcement')
			);

		} else if ($type === 'statusMap') {
			$statuses = array_flip(Configure::read('Forum.statusMap'));
			$options = array();

			foreach ($statuses as $key => $status) {
				$options[$key] = __d('forum', 'status.' . $status);
			}
		}

		if (isset($options[$value])) {
			return $options[$value];
		} else {
			return $options;
		}
	}

	/**
	 * Return the report type as a string name.
	 *
	 * @param int $type
	 * @return string
	 */
	public function reportType($type) {
		$types = array(
			1 => __d('forum', 'Topic'),
			2 => __d('forum', 'Post'),
			3 => __d('forum', 'User')
		);

		return $types[$type];
	}

	/**
	 * Get the users timezone.
	 *
	 * @return string
	 */
	public function timezone() {
		if ($this->Session->check('Forum.Profile.timezone')) {
			return $this->Session->read('Forum.Profile.timezone');
		} else {
			return Configure::read('Forum.settings.defaultTimezone');
		}
	}

	/**
	 * Determine the topic icon state.
	 *
	 * @param array $topic
	 * @return string
	 */
	public function topicIcon($topic) {
		$lastVisit = $this->Session->read('Forum.lastVisit');
		$readTopics = $this->Session->read('Forum.readTopics');

		if (!is_array($readTopics)) {
			$readTopics = array();
		}

		$icon = 'open';

		if (isset($topic['LastPost']['created'])) {
			$lastPost = $topic['LastPost']['created'];
		} else if (isset($topic['Topic']['created'])) {
			$lastPost = $topic['Topic']['created'];
		}

		if (!$topic['Topic']['status']) {
			$icon = 'closed';
		} else {
			if (isset($lastPost) && $lastPost > $lastVisit &&  !in_array($topic['Topic']['id'], $readTopics)) {
				$icon = 'new';
			} else if ($topic['Topic']['type'] == 1) {
				$icon = 'sticky';
			} else if ($topic['Topic']['type'] == 2) {
				$icon = 'important';
			} else if ($topic['Topic']['type'] == 3) {
				$icon = 'announcement';
			}
		}

		if ($icon === 'open' || $icon === 'new') {
			if ($topic['Topic']['post_count'] >= Configure::read('Forum.settings.postsTillHotTopic')) {
				$icon .= '_hot';
			}
		}

		return $this->Html->image('/forum/img/topic_' . $icon . '.png', array(
			'alt' => ucfirst($icon)
		));
	}

	/**
	 * Get the amount of pages for a topic.
	 *
	 * @param array $topic
	 * @return array
	 */
	public function topicPages($topic) {
		if (empty($topic['page_count'])) {
			$postsPerPage = Configure::read('Forum.settings.postsPerPage');
			$topic['page_count'] = ($topic['post_count'] > $postsPerPage) ? ceil($topic['post_count'] / $postsPerPage) : 1;
		}

		$topicPages = array();

		for ($i = 1; $i <= $topic['page_count']; ++$i) {
			$topicPages[] = $this->Html->link($i, array('controller' => 'topics', 'action' => 'view', $topic['slug'], 'page' => $i));
		}

		if ($topic['page_count'] > Configure::read('Forum.settings.topicPagesTillTruncate')) {
			array_splice($topicPages, 2, $topic['page_count'] - 4, '...');
		}

		return $topicPages;
	}

	/**
	 * Get the type of topic.
	 *
	 * @param int $type
	 * @return string
	 */
	public function topicType($type = null) {
		if (!$type) {
			return '';
		}

		$types = $this->options('topicTypes');

		return $this->output('<strong>' . $types[$type] . '</strong>');
	}

}