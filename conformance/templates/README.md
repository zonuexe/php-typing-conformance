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
| `detail.phtml` | One per-test page |
| `highlight.phtml` | Shiki syntax highlighting for the detail pages |

`report.css` is copied next to the generated pages rather than rendered.

The analyzer table's contents are not in the template: each tool is a class
under `src/Metadata/Analyzer/`, holding neutral data (names, URLs, enums) with
no markup in it, and the latest release of each is injected from
`data/analyzer-releases.toml`. `analyzers.phtml` decides what becomes a link,
an `<abbr>` or a `<time>`.
