<?php

namespace MetzWeb\Instagram;

/**
 * Instagram API class
 *
 * API Documentation: http://instagram.com/developer/
 * Class Documentation: https://github.com/cosenary/Instagram-PHP-API
 *
 * @author Christian Metz
 * @since 30.10.2011
 * @copyright Christian Metz - MetzWeb Networks 2011-2014
 * @version 2.2
 * @license BSD http://www.opensource.org/licenses/bsd-license.php
 */
class Instagram
{
    /**
     * The API base URL.
     */
    const API_URL = 'https://api.instagram.com/v1/';

    /**
     * The API OAuth URL.
     */
    const API_OAUTH_URL = 'https://api.instagram.com/oauth/authorize';

    /**
     * The OAuth token URL.
     */
    const API_OAUTH_TOKEN_URL = 'https://api.instagram.com/oauth/access_token';

    /**
     * The Instagram API Key.
     *
     * @var string
     */
    private $_apikey;

    /**
     * The Instagram OAuth API secret.
     *
     * @var string
     */
    private $_apisecret;

    /**
     * The callback URL.
     *
     * @var string
     */
    private $_callbackurl;

    /**
     * The user access token.
     *
     * @var string
     */
    private $_accesstoken;

    /**
     * Whether a signed header should be used.
     *
     * @var bool
     */
    private $_signedheader = false;

    /**
     * Available scopes.
     *
     * @var string[]
     */
    private $_scopes = array('basic', 'likes', 'comments', 'relationships');

    /**
     * Available actions.
     *
     * @var string[]
     */
    private $_actions = array('follow', 'unfollow', 'block', 'unblock', 'approve', 'deny');

    /**
     * Rate limit.
     *
     * @var int
     */
    private $_xRateLimitRemaining;

    /**
     * Default constructor.
     *
     * @param array|string $config Instagram configuration data
     *
     * @return void
     *
     * @throws \MetzWeb\Instagram\InstagramException
     */
    public function __construct($config)
    {
        if (is_array($config)) {
            // if you want to access user data
            $this->setApiKey($config['apiKey']);
            $this->setApiSecret($config['apiSecret']);
            $this->setApiCallback($config['apiCallback']);
        } elseif (is_string($config)) {
            // if you only want to access public data
            $this->setApiKey($config);
        } else {
            throw new InstagramException('Error: __construct() - Configuration data is missing.');
        }
    }

    /**
     * Generates the OAuth login URL.
     *
     * @param string[] $scopes Requesting additional permissions
     *
     * @return string Instagram OAuth login URL
     *
     * @throws \MetzWeb\Instagram\InstagramException
     */
    public function getLoginUrl($scopes = array('basic'))
    {
        if (is_array($scopes) && count(array_intersect($scopes, $this->_scopes)) === count($scopes)) {
            return self::API_OAUTH_URL . '?client_id=' . $this->getApiKey() . '&redirect_uri=' . urlencode($this->getApiCallback()) . '&scope=' . implode('+',
                $scopes) . '&response_type=code';
        }

        throw new InstagramException("Error: getLoginUrl() - The parameter isn't an array or invalid scope permissions used.");
    }

    /**
     * Search for a user.
     *
     * @param string $name Instagram username
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function searchUser($name, $limit = 0)
    {
        $params = array();

        $params['q'] = $name;
        if ($limit > 0) {
            $params['count'] = $limit;
        }

        return $this->_makeCall('users/search', false, $params);
    }

    /**
     * Get user info.
     *
     * @param int $id Instagram user ID
     *
     * @return mixed
     */
    public function getUser($id = 0)
    {
        $auth = false;

        if ($id === 0 && isset($this->_accesstoken)) {
            $id = 'self';
            $auth = true;
        }

        return $this->_makeCall('users/' . $id, $auth);
    }

    /**
     * Get user activity feed.
     *
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function getUserFeed($limit = 0)
    {
        $params = array();
        if ($limit > 0) {
            $params['count'] = $limit;
        }

        return $this->_makeCall('users/self/feed', true, $params);
    }

    /**
     * Get user recent media.
     *
     * @param int|string $id Instagram user ID
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function getUserMedia($id = 'self', $limit = 0)
    {
        $params = array();

        if ($limit > 0) {
            $params['count'] = $limit;
        }

        return $this->_makeCall('users/' . $id . '/media/recent', strlen($this->getAccessToken()), $params);
    }

    /**
     * Get the liked photos of a user.
     *
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function getUserLikes($limit = 0)
    {
        $params = array();

        if ($limit > 0) {
            $params['count'] = $limit;
        }

        return $this->_makeCall('users/self/media/liked', true, $params);
    }

    /**
     * Get the list of users this user follows
     *
     * @param int|string $id Instagram user ID.
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function getUserFollows($id = 'self', $limit = 0)
    {
        $params = array();

        if ($limit > 0) {
            $params['count'] = $limit;
        }

        return $this->_makeCall('users/' . $id . '/follows', true, $params);
    }

    /**
     * Get the list of users this user is followed by.
     *
     * @param int|string $id Instagram user ID
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function getUserFollower($id = 'self', $limit = 0)
    {
        $params = array();

        if ($limit > 0) {
            $params['count'] = $limit;
        }

        return $this->_makeCall('users/' . $id . '/followed-by', true, $params);
    }

    /**
     * Get information about a relationship to another user.
     *
     * @param int $id Instagram user ID
     *
     * @return mixed
     */
    public function getUserRelationship($id)
    {
        return $this->_makeCall('users/' . $id . '/relationship', true);
    }

    /**
     * Get the value of X-RateLimit-Remaining header field.
     *
     * @return int X-RateLimit-Remaining API calls left within 1 hour
     */
    public function getRateLimit()
    {
        return $this->_xRateLimitRemaining;
    }

    /**
     * Modify the relationship between the current user and the target user.
     *
     * @param string $action Action command (follow/unfollow/block/unblock/approve/deny)
     * @param int $user Target user ID
     *
     * @return mixed
     *
     * @throws \MetzWeb\Instagram\InstagramException
     */
    public function modifyRelationship($action, $user)
    {
        if (in_array($action, $this->_actions) && isset($user)) {
            return $this->_makeCall('users/' . $user . '/relationship', true, array('action' => $action), 'POST');
        }

        throw new InstagramException('Error: modifyRelationship() | This method requires an action command and the target user id.');
    }

    /**
     * Search media by its location.
     *
     * @param float $lat Latitude of the center search coordinate
     * @param float $lng Longitude of the center search coordinate
     * @param int $distance Distance in metres (default is 1km (distance=1000), max. is 5km)
     * @param long $minTimestamp Media taken later than this timestamp (default: 5 days ago)
     * @param long $maxTimestamp Media taken earlier than this timestamp (default: now)
     *
     * @return mixed
     */
    public function searchMedia($lat, $lng, $distance = 1000, $minTimestamp = null, $maxTimestamp = null)
    {
        return $this->_makeCall('media/search', false, array(
            'lat' => $lat,
            'lng' => $lng,
            'distance' => $distance,
            'min_timestamp' => $minTimestamp,
            'max_timestamp' => $maxTimestamp
        ));
    }

    /**
     * Get media by its id.
     *
     * @param int $id Instagram media ID
     *
     * @return mixed
     */
    public function getMedia($id)
    {
        return $this->_makeCall('media/' . $id, isset($this->_accesstoken));
    }

    /**
     * Get the most popular media.
     *
     * @return mixed
     */
    public function getPopularMedia()
    {
        return $this->_makeCall('media/popular');
    }

    /**
     * Search for tags by name.
     *
     * @param string $name Valid tag name
     *
     * @return mixed
     */
    public function searchTags($name)
    {
        return $this->_makeCall('tags/search', false, array('q' => $name));
    }

    /**
     * Get info about a tag
     *
     * @param string $name Valid tag name
     *
     * @return mixed
     */
    public function getTag($name)
    {
        return $this->_makeCall('tags/' . $name);
    }

    /**
     * Get a recently tagged media.
     *
     * @param string $name Valid tag name
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function getTagMedia($name, $limit = 0)
    {
        $params = array();

        if ($limit > 0) {
            $params['count'] = $limit;
        }

        return $this->_makeCall('tags/' . $name . '/media/recent', false, $params);
    }

    /**
     * Get a list of users who have liked this media.
     *
     * @param int $id Instagram media ID
     *
     * @return mixed
     */
    public function getMediaLikes($id)
    {
        return $this->_makeCall('media/' . $id . '/likes', true);
    }

    /**
     * Get a list of comments for this media.
     *
     * @param int $id Instagram media ID
     *
     * @return mixed
     */
    public function getMediaComments($id)
    {
        return $this->_makeCall('media/' . $id . '/comments', false);
    }

    /**
     * Add a comment on a media.
     *
     * @param int $id Instagram media ID
     * @param string $text Comment content
     *
     * @return mixed
     */
    public function addMediaComment($id, $text)
    {
        return $this->_makeCall('media/' . $id . '/comments', true, array('text' => $text), 'POST');
    }
  private $_scopes = array('basic', 'public_content', 'likes', 'comments', 'relationships');

}
