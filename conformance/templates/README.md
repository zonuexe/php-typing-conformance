# Templates

HTML and other report templates for the conformance runner live here.

`Conformance\Reporting\SummaryReport` decides what each page says and hands
plain data to these plain-PHP templates, which decide how it is marked up.
Escaping goes through the global `h()` / `h_inline()` / `h_noted()` … helpers
in `src/functions.php`; see the docblock at the top of each file for the
variables it expects.

| File | What it renders |
| --- | --- |
| `page.phtml` | The skeleton shared by every page |
| `index.phtml` | The index body: prose, and the sections below |
| `legend.phtml` | What the matrix cell values mean |
| `matrix.phtml` | One results matrix (soundness, or style) |
| `analyzers.phtml` | Reference table of the analyzers |
| `language-servers.phtml` | Reference table of the language servers |
| `language-server-capabilities.phtml` | Measured LSP capability matrix and hover type conformance, from `results/lsp/*.toml` |
| `detail.phtml` | One per-test page |
| `highlight.phtml` | Shiki syntax highlighting for the detail pages |

`report.css` is copied next to the generated pages rather than rendered.

Neither reference table's contents are in its template: each tool is a class
under `src/Metadata/Analyzer/` or `src/Metadata/LanguageServer/`, holding
neutral data (names, URLs, enums) with no markup in it, and the latest release
of each is injected from `data/releases.toml`. The templates decide what
becomes a link, an `<abbr>`, a `<time>`, a dash or the words "Not stated".
