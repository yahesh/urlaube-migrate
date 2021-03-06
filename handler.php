<?php

  /**
    This is a custom migration plugin.

    @package yahesh\urlaube-migrate
    @version 0.1a0
    @author  Yahe <hello@yahe.sh>
    @since   0.1a0
  */

  // ===== DO NOT EDIT HERE =====

  // prevent script from getting called directly
  if (!defined("URLAUBE")) { die(""); }

  class UrlaubeMigrateHandler extends BaseSingleton implements Handler {

    // CONSTANTS

    const REGEX = "~^.*$~";

    // INTERFACE FUNCTIONS

    public static function getContent($metadata, &$pagecount) {
      return null;
    }

    public static function getUri($metadata) {
      return null;
    }

    public static function parseUri($uri) {
      return null;
    }

    // HELPER FUNCTION

    protected static function parseArchive($uri) {
      return preparecontent(parseuri($uri,
                                     "~^\/archive\/".
                                     "((?P<year>[0-9]+)\/)?".
                                     "((?P<month>[0-9]+)\/)?".
                                     "((?P<day>[0-9]+)\/)?".
                                     "(page=(?P<page>[0-9]+)\/)?".
                                     "$~"),
                            [ArchiveHandler::YEAR  => null,
                             ArchiveHandler::MONTH => null,
                             ArchiveHandler::DAY   => null,
                             PAGE                  => 1],
                            null);
    }

    protected static function parseAuthor($uri) {
      return preparecontent(parseuri($uri,
                                     "~^\/author\/".
                                     "((?P<author>[0-9A-Za-z\-]+)\/)".
                                     "(page=(?P<page>[0-9]+)\/)?".
                                     "$~"),
                            [PAGE => 1],
                            [AUTHOR]);
    }

    protected static function parseCategory($uri) {
      return preparecontent(parseuri($uri,
                                     "~^\/category\/".
                                     "((?P<category>[0-9A-Za-z\-]+)\/)".
                                     "(page=(?P<page>[0-9]+)\/)?".
                                     "$~"),
                            [PAGE => 1],
                            [CATEGORY]);
    }

    protected static function parseFeed($uri) {
      return preparecontent(parseuri(relativeuri(),
                                     "~^\/feed\/".
                                     "((?P<suburl>[0-9A-Za-z\-\/]*))".
                                     "$~"),
                            [FeedHandler::SUBURL => US],
                            null);
    }

    protected static function parsePage($uri) {
      return preparecontent(parseuri($uri,
                                     "~^\/[0-9]+\/".
                                     "((?P<name>[0-9A-Za-z\-]+)\/)".
                                     "$~"),
                            null,
                            [PageHandler::NAME]);
    }

    // RUNTIME FUNCTIONS

    public static function run() {
      $result = false;

      // check if we're handling an old archive URL
      if (!$result) {
        $metadata = static::parseArchive(relativeuri());
        if ($metadata instanceof Content) {
          if (null !== preparecontent(ArchiveHandler::getContent($metadata, $pagecount))) {
            $result = relocate(ArchiveHandler::getUri($metadata), true, true);
          }
        }
      }

      // check if we're handling an old author URL
      if (!$result) {
        $metadata = static::parseAuthor(relativeuri());
        if ($metadata instanceof Content) {
          if (null !== preparecontent(AuthorHandler::getContent($metadata, $pagecount))) {
            $result = relocate(AuthorHandler::getUri($metadata), true, true);
          }
        }
      }

      // check if we're handling an old category URL
      if (!$result) {
        $metadata = static::parseCategory(relativeuri());
        if ($metadata instanceof Content) {
          if (null !== preparecontent(CategoryHandler::getContent($metadata, $pagecount))) {
            $result = relocate(CategoryHandler::getUri($metadata), true, true);
          }
        }
      }

      // check if we're handling an old feed URL
      if (!$result) {
        $metadata = static::parseFeed(relativeuri());
        if ($metadata instanceof Content) {
          // check that the SUBURL starts and ends with a slash
          $suburl = trail(lead(value($metadata, FeedHandler::SUBURL), US), US);

          // fix the suburl field
          $metadata->set(FeedHandler::SUBURL, $suburl);

          // check if we have an archive feed
          $subdata = static::parseArchive($suburl);
          if ($subdata instanceof Content) {
            // store the name of the feed source
            $metadata->set(FeedHandler::FEED, ArchivHandler::class);
          } else {
            // check if we have an author feed
            $subdata = static::parseAuthor($suburl);
            if ($subdata instanceof Content) {
              // store the name of the feed source
              $metadata->set(FeedHandler::FEED, AuthorHandler::class);
            } else {
              // check if we have a category feed
              $subdata = static::parseCategory($suburl);
              if ($subdata instanceof Content) {
                // store the name of the feed source
                $metadata->set(FeedHandler::FEED, CategoryHandler::class);
              }
            }
          }

          // only proceed when we found the matching handler
          if ($subdata instanceof Content) {
            // store the metadata of the feed source
            $metadata->merge($subdata);

            if (null !== preparecontent(FeedHandler::getContent($metadata, $pagecount))) {
              $result = relocate(FeedHandler::getUri($metadata), true, true);
            }
          }
        }
      }

      // check if we're handling an old page URL
      if (!$result) {
        $metadata = static::parsePage(relativeuri());
        if ($metadata instanceof Content) {
          if (null !== preparecontent(PageHandler::getContent($metadata, $pagecount))) {
            $result = relocate(PageHandler::getUri($metadata), true, true);
          }
        }
      }

      return $result;
    }

  }

  // register handler
  Handlers::register(UrlaubeMigrateHandler::class, "run", UrlaubeMigrateHandler::REGEX, [GET, POST], ERROR);
