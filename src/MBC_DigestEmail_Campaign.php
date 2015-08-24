<?php
/**
 * MBC_DigestEmail_Campaign
 * 
 */
namespace DoSomething\MBC_DigestEmail;

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;
use \Exception;

/**
 * MBC_DigestEmail_Campaign class - 
 */
class MBC_DigestEmail_Campaign {

  /**
   * Singleton instance of application configuration settings.
   *
   * @var object
   */
   private $mbConfig;

  /**
   * Singleton instance of class used to report usage statistics.
   *
   * @var object
   */
   private $statHat;

  /**
   * A collection of tools used by all of the Message Broker applications.
   *
   * @var object
   */
   private $mbToolbox;

  /**
   *
   *
   * @var
   */
   private $title;

  /**
   * Needs public scope to allow making reference to campaign nid when assigning campaigns
   * to user objects.
   *
   * @var integer
   */
   public $drupal_nid;

  /**
   * A flag to determine if the campaign has "staff pick" status. Used for sorting of
   * campaigns in user digest messages.
   *
   * @var boolean
   */
   private $is_staff_pick;

  /**
   *
   *
   * @var string
   */
   private $url;

  /**
   *
   *
   * @var string
   */
   private $image_campaign_cover;

  /**
   * Campaign text displayed in summary listings to encourage users to take up the
   * "call to action".
   *
   * @var string
   */
   private $call_to_action;

  /**
   * The problem that will be addressed by doing the campaign. Used in descriptive text in
   * digest message campaign listings.
   *
   * @var string
   */
   private $fact_problem;

  /**
   *
   *
   * @var string
   */
   private  $fact_solution;

  /**
   * Special message from campaign manager about the campaign. Presence of this messages overrides
   * all other campaign descriptive text.
   *
   * @var string
   */
   private $latest_news;

  /**
   *
   *
   * @var string
   */
   private $during_tip_header;

  /**
   *
   *
   * @var string
   */
   private $during_tip;

  /**
   *
   *
   * @var
   */
   private $markup;

  /**
   * __construct(): Trigger populating values in Campaign object when object is created.
   *
   * @param integer $nid
   *   nid (Drupal node ID) of the campaign content item.
   */
  function __construct($nid) {

    $this->mbConfig = MB_Configuration::getInstance();
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->mbToolboxcURL = $this->mbConfig->getProperty('mbToolboxcURL');

    $this->add($nid);
    // $this->generateMarkup($nid);
  }

  /**
   * Populate object properties based on campaign lookup on Drupal site.
   *
   *
   */
  private function add($nid) {

    $campaignSettings = $this->gatherSettings($nid);

    $this->drupal_nid = $campaignSettings->nid;
    $this->url = 'http://www.dosomething.org/node/' . $campaignSettings->nid . '#prove';

    // Title - required
    if (isset($campaignSettings->title)) {
      $this->title = $campaignSettings->title;
    }
    else {
      echo 'MBC_DigestEmail_Campaign->add(): Campaign ' . $nid . ' title not set.', PHP_EOL;
      throw new Exception('Unable to create Campaign object : ' . $nid . ' title not set.');
    }
    // image_cover->src - required
    if (isset($campaignSettings->image_cover->src)) {
      $this->image_campaign_cover = $campaignSettings->image_cover->src;
    }
    else {
      echo 'MBC_DigestEmail_Campaign->add(): Campaign ' . $nid . ' (' . $this->title . ') image_cover->src not set.', PHP_EOL;
      throw new Exception('Unable to create Campaign object : ' . $nid . ' (' . $this->title . ') image_cover->src not set.');
    }
    // call_to_action - nice to have but not a show stopper
    if (isset($campaignSettings->call_to_action)) {
      $this->call_to_action = $campaignSettings->call_to_action;
    }
    else {
      echo 'MBC_DigestEmail_Campaign->add(): Campaign ' . $nid . ' (' . $this->title . ') call_to_action not set.', PHP_EOL;
    }
    // DO IT: During Tip Header - step_pre[0]->header - nice to have but not a show stopper
    if (isset($campaignSettings->step_pre[0]->header)) {
      $this->during_tip_header = $campaignSettings->step_pre[0]->header;
    }
    else {
      echo 'MBC_DigestEmail_Campaign->add(): Campaign ' . $nid . ' (' . $this->title . ') DO IT: During Tip Header, step_pre[0]->header not set.', PHP_EOL;
    }
    // DO IT: During Tip Copy - step_pre[0]->copy - nice to have but not a show stopper
    if (isset($campaignSettings->step_pre[0]->copy)) {
      $this->during_tip_copy = strip_tags($campaignSettings->step_pre[0]->copy);
    }
    else {
      echo 'MBC_DigestEmail_Campaign->add(): Campaign ' . $nid . ' (' . $this->title . ') DO IT: During Tip Copy, step_pre[0]->copy not set.', PHP_EOL;
    }

    // Optional
    // is_staff_pick
    if (isset($campaignSettings->is_staff_pick)) {
      $this->is_staff_pick = $campaignSettings->is_staff_pick;
    }
    // latest_news_copy - replaces Tip copy if set.
    if (isset($campaignSettings->latest_news_copy)) {
      $this->latest_news = strip_tags($campaignSettings->latest_news_copy);
    }
  }

  /**
   * Gather campaign properties based on campaign lookup on Drupal site.
   *
   * @param integer $nid
   *   The Drupal nid (node ID) of the terget campaign.
   *
   * @return object
   *   The returned results from the call to the campaign endpoint on the Drupal site.
   *   Return boolean FALSE if request is unsuccessful.
   */
  private function gatherSettings($nid) {

    $dsDrupalAPIConfig = $this->mbConfig->getProperty('ds_drupal_api_config');
    $curlUrl = $dsDrupalAPIConfig['host'];
    $port = isset($dsDrupalAPIConfig['port']) ? $dsDrupalAPIConfig['port'] : NULL;
    if ($port != 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }

    $campaignAPIUrl = $curlUrl . '/api/v1/content/' . $nid;
    $result = $this->mbToolboxcURL->curlGET($campaignAPIUrl);

    // Exclude campaigns that don't have details in Drupal API or "Access
    // denied" due to campaign no longer published
    if ($result[1] == 200 && is_object($result[0])) {
      return $result[0];
    }
    elseif ($result[1] == 200 && is_array($result[0])) {
      throw new Exception('Call to ' . $campaignAPIUrl . ' returned rejected response.' . $nid);
    }
    elseif ($result[1] == 403) {
      throw new Exception('Call to ' . $campaignAPIUrl . ' returned rejected response: ' . $result[0][0] . '.' . $nid);
    }
    else {
      throw new Exception('Unable to call ' . $campaignAPIUrl . ' to get Campaign object: ' . $nid);
    }
  }
}
