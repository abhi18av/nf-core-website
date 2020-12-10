<?php

//
// Convert Markdown to HTML
//

// Common functions
require_once('functions.php');

// Markdown parsing libraries
require_once(dirname(__FILE__).'/libraries/parsedown/Parsedown.php');
require_once(dirname(__FILE__).'/libraries/parsedown-extra/ParsedownExtra.php');
function parse_md($markdown){
  global $md_trim_before;
  global $md_trim_after;
  global $md_content_replace;
  global $_GET;
  global $src_url_prepend;
  global $href_url_prepend;
  global $href_url_suffix_cleanup;
  global $html_content_replace;
  global $no_auto_toc;
  global $title;
  global $subtitle;

  $output = array();
  // Load the docs markdown
  if(substr($markdown, -3) == '.md'){
    $md_full = file_get_contents($markdown);
    if ($md_full === false) {
      header('HTTP/1.1 404 Not Found');
      include('404.php');
      die();
    }
  } else {
    $md_full = $markdown;
  }
  // Get the meta
  $meta = [];
  $md = $md_full;
  $fm = parse_md_front_matter($md_full);
  $meta = $fm['meta'];
  $md = $fm['md'];
  if(isset($meta['title'])){
    $title = $meta['title'];
  }
  if(isset($meta['subtitle'])){
    $subtitle = $meta['subtitle'];
  }

  // Trim off any content if requested
  if(isset($md_trim_before) && $md_trim_before){
    // Only trim if the string exists
    if(stripos($md, $md_trim_before)){
      $md = stristr($md, $md_trim_before);
    }
  }
  if(isset($md_trim_after) && $md_trim_after){
    if(stripos($md, $md_trim_after)){
      $md = stristr($md, $md_trim_after);
    }
  }

  // Find and replace markdown content if requested
  if(isset($md_content_replace)){
    foreach($md_content_replace as $repl){
      $md = preg_replace($repl[0], $repl[1], $md);
    }
  }

  // Format Nextflow code blocks as Groovy
  $md = preg_replace('/```nextflow/i', '```groovy', $md);

  // Convert to HTML
  $pd = new ParsedownExtra();
  $content = $pd->text($md);

  // Highlight any search terms if we have them
  if(isset($_GET['q']) && strlen($_GET['q'])){
    $content = preg_replace("/(".$_GET['q'].")/i", "<mark>$1</mark>", $content);
  }

  // Automatically add HTML IDs to headers
  // Add ID attributes to headers
  $content = add_ids_to_headers($content);

  // Prepend to src URLs if configureds and relative
  if(isset($src_url_prepend)){
    $content = preg_replace('/src="(?!https?:\/\/)([^"]+)"/i', 'src="'.$src_url_prepend.'$1"', $content);
  }
  // Prepend to href URLs if configureds and relative
  if(isset($href_url_prepend)){
    $content = preg_replace('/href="(?!https?:\/\/)(?!#)([^"]+)"/i', 'href="'.$href_url_prepend.'$1"', $content);
  }
  // Clean up href URLs if configured
  if(isset($href_url_suffix_cleanup)){
    $content = preg_replace('/href="(?!https?:\/\/)(?!#)([^"]+)'.$href_url_suffix_cleanup.'"/i', 'href="$1"', $content);
  }
  // Add CSS classes to tables
  $content = str_replace('<table>', '<div class="table-responsive"><table class="table table-bordered table-striped table-sm small">', $content);
  $content = str_replace('</table>', '</table></div>', $content);

  // Find and replace HTML content if requested
  if(isset($html_content_replace)){
    $content = str_replace($html_content_replace[0], $html_content_replace[1], $content);
  }

  // Find and replace emojis names with images
  $content = preg_replace('/:(?!\/)([\S]+?):/','<img class="emoji" alt="${1}" height="20" width="20" src="https://github.githubassets.com/images/icons/emoji/${1}.png">',$content);

  if (!isset($no_auto_toc) & !$no_auto_toc & preg_match_all('~<h([1-6].*?)>(.*?)</h([1-6])>~Uis', $content, $matches) > 0) {
    # main row + content
    $content = '<div class="row"><div class="col-12 col-lg-9">
                      <div class="rendered-markdown publication-page-content">' . $content . '</div>
                </div>';
    # sidebar
    $content .= '<div class="col-12 col-lg-3 pl-2"><div class="side-sub-subnav sticky-top">';
    # ToC
    $content .= '<nav class="toc">';
    $content .= generate_toc($content);
    $content .=  '<p class="small text-right"><a href=" #" class="text-muted"><i class="fas fa-arrow-to-top"></i> Back to top</a></p>';
    $content .=  '</nav>';

    $content .= '</div></div>'; # end of the sidebar col
    $content .=  '</div>'; # end of the row
  }

  $output["content"] = $content;
  $output["meta"] = $meta;
  $output["title"] = $title;
  $output["subtitle"] = $subtitle;
  return $output;
}
