<?xml version='1.0' encoding='ISO-8859-1'?>

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns="http://www.w3.org/1999/xhtml"
                version="1.0">

  <xsl:import href="blfs-chunked2.xsl"/>
  <xsl:import href="http://docbook.sourceforge.net/release/xsl/1.69.1/xhtml/chunk-common.xsl"/>
  <xsl:include href="http://docbook.sourceforge.net/release/xsl/1.69.1/xhtml/manifest.xsl"/>

    <!--  From the original chunk.xsl file:

    Why is chunk-code now xsl:included?

    Suppose you want to customize *both* the chunking algorithm used *and* the
    presentation of some elements that may be chunks. In order to do that, you
    must get the order of imports "just right". The answer is to make your own
    copy of this file, where you replace the initial import of "docbook.xsl"
    with an import of your own base.xsl (that does its own import of docbook.xsl).

    Put the templates for changing the presentation of elements in your base.xsl.

    Put the templates that control chunking after the include of chunk-code.xsl.

    Voila! (Man I hope we can do this better in XSLT 2.0)  -->

  <xsl:include href="http://docbook.sourceforge.net/release/xsl/1.69.1/xhtml/chunk-code.xsl"/>

    <!-- Including our others customized chunks templates -->
  <xsl:include href="xhtml/lfs-index.xsl"/>
  <xsl:include href="xhtml/lfs-legalnotice.xsl"/>
  <xsl:include href="xhtml/lfs-navigational.xsl"/>

  <!-- Prevent creation of dummy files -->
  <xsl:template match="sect1|sect2|sect3|sect4|sect5|section">
    <xsl:variable name="ischunk">
      <xsl:call-template name="chunk"/>
    </xsl:variable>
    <xsl:choose>
      <xsl:when test="@role = 'dummy'"/>
      <xsl:when test="not(parent::*)">
        <xsl:call-template name="process-chunk-element"/>
      </xsl:when>
      <xsl:when test="$ischunk = 0">
        <xsl:apply-imports/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:call-template name="process-chunk-element"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

</xsl:stylesheet>
