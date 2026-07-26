<?php

declare(strict_types=1);

namespace Conformance\Metadata;

use function preg_match;
use function trim;

/**
 * What one analyzer *is* — as opposed to how it scores, which the results
 * matrix answers.
 *
 * Neutral data throughout: no markup, no link syntax, no display strings
 * assembled here. A name is a name and a URL is a URL; whether either becomes
 * an anchor, an <abbr> or a <time> is the template's business. That is what
 * lets the same record feed a second output format later without unpicking
 * HTML out of it.
 *
 * ## Curated here, injected from outside
 *
 * Everything a subclass hardcodes is a curated judgement or a stable fact that
 * only changes when the project changes: who founded it, what entity is behind
 * it, what it parses with. The one part that goes stale by itself — the latest
 * release and its date — is constructor-injected instead, so refreshing it
 * never touches a line of curation. See [[Release]] and [[ReleaseTable]].
 *
 * ## The columns, and what they are allowed to claim
 *
 * Every tool here is a static analyzer, so "static analyzer" is not a
 * classification — it is the entry criterion. What actually distinguishes them
 * are three independent axes, and the old single "Kind" column picked a
 * different one for each row (Intelephense by interface, NoVerify by analysis,
 * Mago by packaging), which made the values look mutually exclusive when they
 * are not. Hence [[AnalysisKind]], [[InterfaceKind]] and bundled() as three
 * separate answers.
 *
 * Founder names each individual who started the project and links them to
 * their own profile, verified rather than guessed. A parenthetical that only
 * names the linked GitHub handle or org adds nothing the link already shows,
 * so founderEmployer() carries an employer — Etsy, Vimeo, VK — and nothing
 * else.
 *
 * Organization and lead maintainer are deliberately two columns rather than
 * one. A single "Current maintainer" column mixed two different questions and
 * answered whichever happened to have an answer: an org name for some rows, a
 * person's name for the rows where maintenance actually passed from the
 * founder to someone else. See [[OrganizationKind]] and [[LeadMaintainer]] for
 * what each of the two now states.
 *
 * expansion() renders the name as an <abbr> tooltip. It is not a strict "the
 * name is short for this" claim for every row — see the Psalm subclass, which
 * uses it for an optional backronym.
 */
abstract class AnalyzerMetadata
{
    /**
     * @param string $tool the runner's name for it, and its results directory
     */
    public function __construct(
        public readonly string $tool,
        public readonly Release $latestRelease,
    ) {
    }

    /**
     * Reduce the tool's own version banner to the version number alone.
     *
     * Every analyzer answers `--version` in its own format — "Phan 6.0.7",
     * "PHPStan - PHP Static Analysis Tool 2.2.5", a bare number — so reading
     * one is per-tool knowledge and belongs with the tool.
     *
     * This is about the version *this suite ran*, recorded in
     * results/<tool>/version.toml, which is a different question from
     * latestRelease: the point of the report is often that they differ.
     */
    final public function shortVersion(string $reportedVersion): string
    {
        $version = trim($reportedVersion);
        $pattern = $this->versionPattern();

        if ($pattern === null || preg_match($pattern, $version, $matches) !== 1) {
            return $version;
        }

        return $matches[1];
    }

    /**
     * Pattern whose first capture group is the version number in this tool's
     * own banner. Null where the banner is the number and nothing else.
     */
    protected function versionPattern(): ?string
    {
        return null;
    }

    /**
     * Where upstream publishes the notes for one release.
     *
     * Not derivable from the other columns: Psalm's home is psalm.dev and its
     * org is github.com/psalm, but its releases are cut under vimeo/psalm.
     * Null where the project publishes no per-release page at all.
     */
    public function releaseUrl(string $version): ?string
    {
        return null;
    }

    /** The project's own name for itself. */
    abstract public function name(): string;

    /** The project's own home on the web. */
    abstract public function homepage(): string;

    abstract public function analysis(): AnalysisKind;

    /** @return non-empty-list<InterfaceKind> */
    abstract public function interfaces(): array;

    /**
     * What else ships in the same artifact — a fixer, a formatter, an
     * architecture guard. Empty where the analyzer is only an analyzer.
     *
     * @return list<string>
     */
    abstract public function bundled(): array;

    /**
     * What the analyzer itself is written in.
     *
     * @return non-empty-list<string>
     */
    abstract public function languages(): array;

    /** @return non-empty-list<Person> */
    abstract public function founders(): array;

    abstract public function organization(): Organization;

    /** SPDX-style expression, or a plain description where no licence applies. */
    abstract public function license(): string;

    /** Calendar year of the first release of *this* artifact. */
    abstract public function initialReleaseYear(): int;

    /** The parser or AST the analyzer is built on. */
    abstract public function parser(): string;

    // Facts not every project has. A subclass states only what applies to it.

    public function expansion(): ?string
    {
        return null;
    }

    /** The employer the founders built it at, where that is the point. */
    public function founderEmployer(): ?string
    {
        return null;
    }

    /** Null where nobody can be named on the evidence available. */
    public function lead(): ?LeadMaintainer
    {
        return null;
    }

    public function announcement(): ?Announcement
    {
        return null;
    }

    /**
     * Where this project announces releases, for update-tools.php to read.
     * Null where nothing is published in a form a script can follow.
     */
    public function releaseFeed(): ?ReleaseFeed
    {
        return null;
    }
}
