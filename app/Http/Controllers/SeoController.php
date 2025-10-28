<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use App\Services\CurlService;
use PDO;


class SeoController extends Controller
{
  
  // Service
  protected $curlService;
  public function __construct(
    CurlService $curlService,
  ) {
    $this->curlService = $curlService;
  }

  public function robotText(Request $request)
  {
    $companyKey = $request->header('COMPANY_KEY');

    $robotTexts = \DB::connection('main')
        ->table('robot_texts')
        ->where('company_id', $companyKey)
        ->first();

    // Default robot text if none found
    $defaultRobotText = "User-agent: *\nDisallow:";

    if (!$robotTexts || empty($robotTexts->description)) {
      return response()->json([
        "code" => "200",
        "message" => "Using default robot text",
        "data" => $defaultRobotText
      ]);
    }

    $robotTextString = trim($robotTexts->description);

    // Validation: ensure it's a non-empty string and reasonable length
    if (!is_string($robotTextString) || strlen($robotTextString) > 2000) {
      return response()->json([
        "code" => "200",
        "message" => "Invalid robot text, using default",
        "data" => $defaultRobotText
      ]);
    }

    return response()->json([
      "code" => "200",
      "message" => "Success",
      "data" => $robotTextString
    ]);
  }

  public function sitemap(Request $request)
  {
    $companyKey = $request->header('COMPANY_KEY');

    $sitemap = \DB::connection('main')
      ->table('sitemaps')
      ->where('company_id', $companyKey)
      ->first();

    if (!$sitemap || empty($sitemap->description)) {
      return response()->json([
        "code" => "404",
        "message" => "Sitemap not found",
        "data" => null
      ], 404);
    }

    // Remove BOM if exists
    $xmlString = preg_replace('/^\xEF\xBB\xBF/', '', $sitemap->description);

    return response()->json([
      "code" => "200",
      "message" => "Success",
      "data" => $xmlString
    ]);
  }


  public function generalSetting(Request $request)
  {
    $companyKey = $request->header('COMPANY_KEY');

    $generalSetting = \DB::connection('main')
        ->table('general_settings')
        ->where('company_id', $companyKey)
        ->first();

    $data = [];
    if ($generalSetting) {
      $data["logo_url"] = $generalSetting->logo_url;
      $data["running_text"] = $generalSetting->running_text;
      $metaContent = $generalSetting->meta;
      $data["head"] = [
        "link" => [],
        "meta" => [],
        "scripts" => []
      ];
      preg_match_all('/<link\b[^>]*>/i', $metaContent, $linkTags);
      if (isset($linkTags[0])) {
        foreach ($linkTags[0] as $tag) {
          $attributes = [];
    
          preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $tag, $matches, PREG_SET_ORDER);
          foreach ($matches as $match) {
            $attrName = $match[1];
            $attrValue = $match[2];
            $attributes[$attrName] = $attrValue;
          }
          $data["head"]["link"][] = $attributes;
            
        }
      }
      preg_match_all('/<meta\b[^>]*>/i', $metaContent, $metaTags);
      if (isset($metaTags[0])) {
        foreach ($metaTags[0] as $tag) {
          $attributes = [];
          preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $tag, $matches, PREG_SET_ORDER);
          foreach ($matches as $match) {
              $attrName = $match[1];
              $attrValue = $match[2];
              $attributes[$attrName] = $attrValue;
          }
          $data["head"]["meta"][] = $attributes;

        }
      }
      preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $metaContent, $jsonLDMatches);
      foreach ($jsonLDMatches[1] as $jsonLD) {
          $data["head"]["scripts"][] = [
              "type" => "application/ld+json",
              "children" => trim($jsonLD)
          ];
      }
      $googleAnalytic = $generalSetting->google_analytic;
      preg_match_all('#<script\b[^>]*>.*?</script>#is', $googleAnalytic, $scriptMatches);
      $scriptsArray = $scriptMatches[0];
      $scriptsString = implode("\n", $scriptsArray);
      $contentWithoutScripts = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $googleAnalytic);
      $contentWithoutScripts = trim($contentWithoutScripts);
      
      $data["google_analytic"] = [
          'scripts' => $scriptsString,
          'contentWithoutScripts' => $contentWithoutScripts,
      ];

      $customFooter = $generalSetting->custom_footer;
      preg_match_all('#<script\b[^>]*>.*?</script>#is', $customFooter, $scriptFooterMatches);
      $scriptsFooterArray = $scriptFooterMatches[0];
      $scriptsFooterString = implode("\n", $scriptsFooterArray);
      $contentWithoutScriptsFooter = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $customFooter);
      $contentWithoutScriptsFooter = trim($contentWithoutScriptsFooter);

      $data["custom_footer"] = [
          'scripts' => $scriptsFooterString,
          'contentWithoutScriptsFooter' => $contentWithoutScriptsFooter,
      ];
    }

    return response()->json([
      "code" => "200",
      "message" => "Success",
      "data" => $data
    ]);
  }

  public function pageMeta(Request $request)
  {
      $companyKey = $request->header('COMPANY_KEY');
      $pageCode   = $request->input('page_code');

      if (empty($companyKey) || empty($pageCode)) {
          return response()->json([
              "code"    => "400",
              "message" => "Missing required parameters",
              "data"    => null
          ], 400);
      }

      $queryString = "
          SELECT 
              pm.title,
              pm.contents,
              pm.meta,
              pm.footer,
              p.code,
              p.name
          FROM page_metas pm
          INNER JOIN pages p 
              ON pm.page_id = p.id
          WHERE pm.company_id = :company_id
            AND p.code = :page_code
      ";

      $stmt = DB::connection('main')->getPdo()->prepare($queryString);
      $stmt->bindParam(':company_id', $companyKey, PDO::PARAM_STR);
      $stmt->bindParam(':page_code', $pageCode, PDO::PARAM_STR);
      $stmt->execute();

      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$result) {
          return response()->json([
              "code"    => "404",
              "message" => "Page not found",
              "data"    => null
          ], 404);
      }
      $data = [];
      $data['code'] = $result['code'];
      $data['name'] = $result['name'];
      $data['title'] = $result['title'];
      $data['footer'] = $result['footer'];
      $metaContent = $result['meta'];
      if ($metaContent) {
          $data["head"] = [
          "link" => [],
          "meta" => [],
          "scripts" => []
        ];
        preg_match_all('/<link\b[^>]*>/i', $metaContent, $linkTags);
        if (isset($linkTags[0])) {
          foreach ($linkTags[0] as $tag) {
            $attributes = [];
      
            preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $tag, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
              $attrName = $match[1];
              $attrValue = $match[2];
              $attributes[$attrName] = $attrValue;
            }
            $data["head"]["link"][] = $attributes;
              
          }
        }
        preg_match_all('/<meta\b[^>]*>/i', $metaContent, $metaTags);
        if (isset($metaTags[0])) {
          foreach ($metaTags[0] as $tag) {
            $attributes = [];
            preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $tag, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $attrName = $match[1];
                $attrValue = $match[2];
                $attributes[$attrName] = $attrValue;
            }
            $data["head"]["meta"][] = $attributes;

          }
        }
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $metaContent, $jsonLDMatches);
        foreach ($jsonLDMatches[1] as $jsonLD) {
            $data["head"]["scripts"][] = [
                "type" => "application/ld+json",
                "children" => trim($jsonLD)
            ];
        }
      }
      
      return response()->json([
          "code"    => "200",
          "message" => "Success",
          "data"    => $data
      ]);
  }


}
