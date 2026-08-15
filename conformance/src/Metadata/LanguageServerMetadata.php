<?php

declare(strict_types=1);

namespace Conformance\Metadata;

/**
 * What one PHP language server claims about itself.
 *
 * ## What a record here is allowed to say
 *
 * IMPORTANT: every value is what the project itself claims, from its README,
 * docs, changelog, release notes, package metadata, or its maintainer's own
 * blog. Except for Intelephense's and Psalm's results in the conformance
 * matrix, nothing in this table has been verified by running the tool —
 * including those two projects' own claims here, which are recorded on the
 * same terms as everyone else's.
 *
 * Where a project does not state something, the record says so by leaving it
 * empty rather than by carrying a guess: languages() returns [] and parser()
 * returns null, which the table renders as "Not stated". The DEVSENSE row is
 * why — its implementation language and parser are inferable from the vendor's
 * history but nowhere actually claimed for this artifact.
 *
 * The *Note() methods carry a hover explanation for the cell above them, and
 * are set only where the short value would otherwise overstate the claim:
 * "Own engine + adapter" needs to say which half is which, "Not stated" needs
 * to say what *is* known instead.
 *
 * Neutral data throughout, like [[AnalyzerMetadata]]: names, URLs, enums and
 * prose, but no markup. bundled() is the one field kept as a sentence rather
 * than a list, because these cells carry qualifiers a list cannot hold —
 * "Psalm CLI, Psalter fixer — one package" is not two items and a third.
 *
 * ## Why this is a separate table from the analyzers
 *
 * The analyzer table's entry criterion is "this tool is a static analyzer we
 * run against the conformance suite"; four of these six have not been run at
 * all, and two of them are not analyzers in the first place — they only drive
 * somebody else's. Merging them would put unrun tools in a table whose whole
 * point is the matrix above it.
 *
 * Intelephense and Psalm have a record in both, as the same artifact in each —
 * unlike the php-lsp/mir and devsense-php-ls/phpy pairs, which are two
 * artifacts apiece. That is not duplication to be cleaned up: both genuinely
 * satisfy both entry criteria, and dropping either from either table would
 * misrepresent it. The analyzer table records how they score; this one records
 * what they are. Psalm makes the case plainly — the LSP server is the same
 * ProjectAnalyzer the CLI runs, so its matrix row and its row here describe one
 * engine reached two ways, and both read their release out of the same entry in
 * the release table.
 *
 * Phan advertises "CLI, LSP" in the analyzer table too, so it qualifies here on
 * the same grounds and is simply not researched yet.
 *
 * Founder, organization and lead maintainer follow the analyzer table's rules
 * exactly — see [[OrganizationKind]] and [[LeadMaintainer]].
 *
 * Which LSP capabilities a server actually advertises is deliberately NOT
 * recorded here: that is a measurement, not a claim, and it lives in
 * results/lsp/<tool>.toml as written by src/run-lsp-probes.php — the report
 * renders it as its own matrix below this table. The division of labour is
 * strict: this class stores what a project says about itself, the probe run
 * stores what its initialize handshake and probe answers actually did.
 */
abstract class LanguageServerMetadata
{
    /**
     * @param string $tool the report's name for it, and its key in the release table
     */
    public function __construct(
        public readonly string $tool,
        public readonly Release $latestRelease,
    ) {
    }

    /** The project's own name for itself. */
    abstract public function name(): string;

    /**
     * A function-specific server: it serves one framework or feature —
     * PHPUnit test cases, a formatter, a debugger — rather than PHP as a
     * language. Such a server has nothing to answer the suite's typing
     * probes with, so it is never launched by run-lsp-probes and never
     * measured; the report lists it for reference only, in its own table.
     */
    public function specialized(): bool
    {
        return false;
    }

    /**
     * A historical server: a dead or superseded implementation, kept in the
     * report as a record of what came before. Like function-specific
     * servers, it is never launched and never measured.
     */
    public function historical(): bool
    {
        return false;
    }

    /** Where the project documents *the server*, which is not always its home page. */
    abstract public function url(): string;

    abstract public function diagnostics(): DiagnosticsSource;

    /**
     * Third-party analyzers the server runs and republishes. Empty where it
     * claims none.
     *
     * @return list<string>
     */
    abstract public function analyzersDriven(): array;

    /** What else ships with the server, as the project describes it. */
    abstract public function bundled(): string;

    /**
     * What the server is written in. Empty where the project does not say.
     *
     * @return list<string>
     */
    abstract public function languages(): array;

    /** @return non-empty-list<Person> */
    abstract public function founders(): array;

    abstract public function organization(): Organization;

    abstract public function license(): string;

    /** Calendar year the server itself first shipped, not the project's. */
    abstract public function initialReleaseYear(): int;

    /** The parser or AST it is built on. Null where the project does not say. */
    abstract public function parser(): ?string;

    // Facts and qualifications not every row has.

    /**
     * Whether the analyzers it drives are its own rather than third-party
     * ones it adapts. Only php-lsp's mir, which has a record in the analyzer
     * table too — marking it keeps that relationship visible without implying
     * php-lsp integrates a foreign analyzer.
     */
    public function analyzersAreInHouse(): bool
    {
        return false;
    }

    /** The employer the founders built it at, where that is the point. */
    public function founderEmployer(): ?string
    {
        return null;
    }

    public function lead(): ?LeadMaintainer
    {
        return null;
    }

    public function announcement(): ?Announcement
    {
        return null;
    }

    public function diagnosticsNote(): ?string
    {
        return null;
    }

    public function analyzersDrivenNote(): ?string
    {
        return null;
    }

    public function bundledNote(): ?string
    {
        return null;
    }

    public function languagesNote(): ?string
    {
        return null;
    }

    public function licenseNote(): ?string
    {
        return null;
    }

    public function initialReleaseNote(): ?string
    {
        return null;
    }

    public function parserNote(): ?string
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
