#! /usr/bin/php
<?php
$dirs = array();
$vers = array();

date_default_timezone_set( "GMT" );
$date = date( "Y-m-d H:i:s" );

// Special cases
$exceptions = array();
//$exceptions[ 'gmp' ] = "UPDIR=/.*(gmp-\d[\d\.-]*\d).*/:DOWNDIR=";

$regex = array();
$regex[ 'bzip2'    ] = "/^.*current version is ([\d\.]+).*$/";
$regex[ 'expect'   ] = "/^.*Download expect([\d\.]+)\.tar.*$/";
$regex[ 'expat'    ] = "/^.*Download expat-([\d\.]+)\.tar.*$/";
$regex[ 'intltool' ] = "/^.*Latest version is (\d[\d\.]+\d).*$/";
$regex[ 'less'     ] = "/^.*current released version is less-(\d+).*$/";
#$regex[ 'mpc'      ] = "/^Version ([\d\.]+).*$/";
$regex[ 'mpc'      ] = "/^(\d\.\d\.\d).*$/";
$regex[ 'mpfr'     ] = "/^mpfr-([\d\.]+)\.tar.*$/";
$regex[ 'sysvinit' ] = "/^.*sysvinit-([\d\.]+)dsf\.tar.*$/";
$regex[ 'tcl-core' ] = "/^.*Download tcl([\d\.]+)-src.*$/";
$regex[ 'tzdata'   ] = "/^.*tzdata([\d]+[a-z]).*$/";
$regex[ 'xz'       ] = "/^.*xz-([\d\.]*\d).*$/";
$regex[ 'zlib'     ] = "/^.*zlib ([\d\.]*\d).*$/";

function find_max( $lines, $regex_match, $regex_replace )
{
  $a = array();
  if ( ! is_array( $lines ) ) return -1;

  foreach ( $lines as $line )
  {
     if ( ! preg_match( $regex_match, $line ) ) continue; 

     // Isolate the version and put in an array
     $slice = preg_replace( $regex_replace, "$1", $line );
     if ( $slice == $line ) continue; 

     array_push( $a, $slice );     
  }

  // SORT_NATURAL requires php-5.4.0 or later
  rsort( $a, SORT_NATURAL );  // Max version is at the top
  return ( isset( $a[0] ) ) ? $a[0] : -2;
}

function http_get_file( $url )
{
  exec( "curl --location --silent --max-time 30 $url", $dir );
//print_r($dir);
  $s   = implode( "\n", $dir );
  $dir = strip_tags( $s );
  return explode( "\n", $dir );
}

function max_parent( $dirpath, $prefix )
{
  // First, remove a directory
  $dirpath  = rtrim  ( $dirpath, "/" );    // Trim any trailing slash
  $position = strrpos( $dirpath, "/" );
  $dirpath  = substr ( $dirpath, 0, $position );

  $lines = http_get_file( $dirpath );

  $regex_match   = "#${prefix}[\d\.]+/#";
  $regex_replace = "#^.*(${prefix}[\d\.]+)/.*$#";
  $max           = find_max( $lines, $regex_match, $regex_replace );

  return "$dirpath/$max"; 
}

function get_packages( $package, $dirpath )
{
  global $exceptions;
  global $regex;

  if ( $package == "vim" ) $dirpath="http://mirrors-usa.go-parts.com/pub/vim/unix";

  // Check for ftp
  if ( preg_match( "/^ftp/", $dirpath ) ) 
  { 
    $dirpath  = substr( $dirpath, 6 );           // Remove ftp://
    $dirpath  = rtrim ( $dirpath, "/" );         // Trim any trailing slash
    $position = strpos( $dirpath, "/" );         // Divide at first slash
    $server   = substr( $dirpath, 0, $position );
    $path     = substr( $dirpath, $position );

    $conn = ftp_connect( $server );
    ftp_login( $conn, "anonymous", "" ); 

    // See if we need special handling
    if ( isset( $exceptions[ $package ] ) )
    {
       $specials = explode( ":", $exceptions[ $package ] );

       foreach ( $specials as $i )
       {
          list( $op, $regexp ) = explode( "=", $i );

          switch ($op)
          {
            case "UPDIR":
              // Remove last dir from $path
              $position = strrpos( $path, "/" );
              $path = substr( $path, 0, $position );

              // Get dir listing
              $lines = ftp_rawlist ($conn, $path);              
              $max   = find_max( $lines, $regexp, $regexp );
              break;

            case "DOWNDIR":
              // Append found directory
              $path .= "/$max";
              break;

            default:
              echo "Error in specials array for $package\n";
              return -5;
              break;
          }
       }
    }

    $lines = ftp_rawlist ($conn, $path);
    ftp_close( $conn );
  }
  else // http
  {
     // Customize http directories as needed
     if ( $package == "tzdata" )
     {
        // Remove two directories
        $dirpath  = rtrim  ( $dirpath, "/" );    // Trim any trailing slash
        $position = strrpos( $dirpath, "/" );
        $dirpath  = substr ( $dirpath, 0, $position );
        $position = strrpos( $dirpath, "/" );
        $dirpath  = substr ( $dirpath, 0, $position );
        //echo "$dirpath\n";
     }

     if ( $package == "mpc" )
     {
        $dirpath = "http://www.multiprecision.org/index.php?prog=mpc&page=download";
     }

     if ( $package == "bzip2" ) 
     {
        // Remove one directory
        $dirpath  = rtrim  ( $dirpath, "/" );    // Trim any trailing slash
        $position = strrpos( $dirpath, "/" );
        $dirpath  = substr ( $dirpath, 0, $position );
        //echo "$dirpath\n";
     }

     if ( $package == "mpfr" )
        $dirpath  = "http://mpfr.loria.fr/mpfr-current";

     if ( $package == "expat" )
        $dirpath  = "http://sourceforge.net/projects/expat/files";

     if ( $package == "intltool" )
        $dirpath  = "https://launchpad.net/intltool/trunk";

     if ( $package == "procps-ng" )
        $dirpath  = "http://sourceforge.net/projects/procps-ng/files";

     if ( $package == "e2fsprogs" )
        $dirpath  = "http://sourceforge.net/projects/e2fsprogs/files/e2fsprogs";

     if ( $package == "expect"    ||
          $package == "flex"      ||
          $package == "psmisc"    )
     {
        $dirpath  = "http://sourceforge.net/projects/$package/files";
     }

     if ( $package == "tcl-core" )
     {
        $dirpath  = "http://sourceforge.net/projects/tcl/files";
     }

     if ( $package == "gcc"        ) $dirpath = max_parent( $dirpath, "gcc-" );
     if ( $package == "util-linux" ) $dirpath = max_parent( $dirpath, "v." );

     $lines = http_get_file( $dirpath );

     if ( ! is_array( $lines ) ) return -6;
  } // End fetch

  if ( isset( $regex[ $package ] ) )
  {
     // Custom search for latest package name
     foreach ( $lines as $l )
     {
        $ver = preg_replace( $regex[ $package ], "$1", $l );
        if ( $ver == $l ) continue;
        return $ver;  // Return first match of regex
     }

     return -7;  // This is an error
  }

  if ( $package == "shadow" )
  {
     return find_max( $lines, "/shadow-/", "/^.*shadow-([\d\.]*\d).*$/" );
  }

  if ( $package == "perl" )  // Custom for perl
  {
     $tmp = array();

     foreach ( $lines as $l )
     {
        if ( preg_match( "/sperl/", $l ) ) continue; // Don't want this
        $ver = preg_replace( "/^.*perl-([\d\.]+\d)\.tar.*$/", "$1", $l );
        if ( $ver == $l ) continue;
        list( $s1, $s2, $rest ) = explode( ".", $ver );
        if ( $s2 % 2 == 1 ) continue; // Remove odd minor versions
        array_push( $tmp, $l );
     }

     $lines = $tmp;
  }

  if ( $package == "attr" ||  
       $package == "acl"  )
  {
     return find_max( $lines, "/$package/", "/^.*$package-([\d\.-]*\d)\.src.*$/" );
  }

  if ( $package == "e2fsprogs" )
     return find_max( $lines, "/v\d/", "/^.*v(\d[\d\.]+\d).*$/" );

  if ( $package == "XML-Parser" )
     return find_max( $lines, "/$package/", "/^.*$package-([\d\._]*\d).tar.*$/" );

  if ( $package == "gmp" )
     return find_max( $lines, "/$package/", "/^.*$package-([\d\._]*\d[a-z]?).tar.*$/" );

  if ( $package == "grub" )
     return find_max( $lines, "/grub/", "/^.*grub-(\d\..*).tar.xz.*$/" );

  // Most packages are in the form $package-n.n.n
  // Occasionally there are dashes (e.g. 201-1)
  return find_max( $lines, "/$package/", "/^.*$package-([\d\.-]*\d)\.tar.*$/" );
}

function get_current()
{
   global $dirs;
   global $vers;

   // Fetech from svn and get wget-list
   $current = array();
   $lfssvn = "svn://svn.linuxfromscratch.org/LFS/trunk";

   $tmpdir = exec( "mktemp -d /tmp/lfscheck.XXXXXX" );
   $cdir   = getcwd();
   chdir( $tmpdir );
   exec ( "svn --quiet export $lfssvn LFS" );
   chdir( $cdir );

   $PAGE       = "$tmpdir/LFS/BOOK/chapter03/chapter03.xml";
   $STYLESHEET = "$tmpdir/LFS/BOOK/stylesheets/wget-list.xsl";

   exec( "xsltproc --xinclude --nonet $STYLESHEET $PAGE", $current );
   exec( "rm -rf $tmpdir" );

   foreach ( $current as $line )
   {
      $file = basename( $line ) . "\n";
      if ( preg_match( "/patch$/", $file ) ) { continue; } // Skip patches

      $file = preg_replace( "/bz2/", '', $file ); // The 2 confusses the regex

      $file        = rtrim( $file );
      $pkg_pattern = "/(\D*).*/";
      //$pattern     = "/\D*(\d.*\d)\D*/";
      $pattern     = "/\D*(\d.*\d)\D*/";

      if ( preg_match( "/e2fsprogs/", $file ) )
      {
        $pattern = "/e2\D*(\d.*\d)\D*/";
        $pkg_pattern = "/(e2\D*).*/";
      }

      else if ( preg_match( "/tzdata/", $file ) )
      {
        $pattern = "/\D*(\d.*[a-z])\.tar\D*/";
      }

      else if ( preg_match( "/gmp/", $file ) )
      {
        $pattern = "/\D*(\d.*[a-z])\.tar\D*/";
      }

      else if ( preg_match( "/eudev.*manpages/", $file ) )
      {
        continue;
      }

      $version = preg_replace( $pattern, "$1", $file );   // Isolate version
      $version = preg_replace( "/^\d-/", "", $version );  // Remove leading #-

      // Touch up package names
      $pkg_name = preg_replace( $pkg_pattern, "$1", $file );
      $pkg_name = trim( $pkg_name, "-" );

      if ( preg_match( "/bzip|iproute/", $pkg_name ) ) { $pkg_name .= "2"; }
      if ( preg_match( "/^m$/"         , $pkg_name ) ) { $pkg_name .= "4"; }
      if ( preg_match( "/shadow/"      , $pkg_name ) ) { $pkg_name  = "shadow"; }

      $dirs[ $pkg_name ] = dirname( $line );
      $vers[ $pkg_name ] = $version;
   }
}

function mail_to_lfs()
{
   global $date;
   global $vers;
   global $dirs;

   $to      = "lfs-book@lists.linuxfromscratch.org";
   $from    = "bdubbs@linuxfromscratch.org";
   $subject = "LFS Package Currency Check - $date GMT";
   $headers = "From: bdubbs@anduin.linuxfromscratch.org";

   $message = "Package         LFS      Upstream  Flag\n\n";

   foreach ( $dirs as $pkg => $dir )
   {
      //if ( $pkg != "gmp" ) continue;  //debug
      $v = get_packages( $pkg, $dir );

      $flag = ( $vers[ $pkg ] != $v ) ? "*" : "";

      // Pad for output
      $pad = "                ";
      $p   = substr( $pkg          . $pad, 0, 15 );
      $l   = substr( $vers[ $pkg ] . $pad, 0, 10 );
      $c   = substr( $v            . $pad, 0, 10 );

      $message .= "$p $l $c $flag\n";
   }

   //echo $message;
   exec ( "echo '$message' | mailx -r $from -s '$subject' $to" );
}

function html()
{

   global $date;
   global $vers;
   global $dirs;

   echo "<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Strict//EN'
                      'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head>
<title>LFS Package Currency Check - $date</title>
<style type='text/css'>
h1, h2 {
   text-align      : center;
}

table {
   border-width    : 1px;
   border-spacing  : 0px;
   border-style    : outset;
   border-color    : gray;
   border-collapse : separate;
   background-color: white;
   margin          : 0px auto;
}

table th {
   border-width    : 1px;
   padding         : 2px;
   border-style    : inset;
   border-color    : gray;
   background-color: white;
}

table td {
   border-width    : 1px;
   padding         : 2px;
   border-style    : inset;
   border-color    : gray;
   background-color: white;
}
</style>

</head>
<body>
<h1>LFS Package Currency Check</h1>
<h2>As of $date GMT</h1>

<table>
<tr><th>LFS Package</th> <th>LFS Version</th> <th>Latest</th> <th>Flag</th></tr>\n";

   // Get the latest version of each package
   foreach ( $dirs as $pkg => $dir )
   {
      $v    = get_packages( $pkg, $dir );
      $flag = ( $vers[ $pkg ] != $v ) ? "*" : "";
      echo "<tr><td>$pkg</td> <td>${vers[ $pkg ]}</td> <td>$v</td> <td>$flag</td></tr>\n";
   }

   echo "</table>
</body>
</html>\n";

}

get_current();  // Get what is in the book
mail_to_lfs();
//html();  // Write html output
?>
