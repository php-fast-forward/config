import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).with_name('prepare-wiki.py')

class PrepareWikiTests(unittest.TestCase):
    def test_resolves_local_pages_and_preserves_external_links_and_anchors(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'classes/Example').mkdir(parents=True)
            (root / 'Home.md').write_text('[Thing](./classes/Example/Thing#run) [Web](https://example.test/help)\n')
            (root / 'classes/Example/Thing.md').write_text('[Home](../../Home) [Part](#run)\n')
            result = subprocess.run([sys.executable, str(SCRIPT), str(root), 'https://github.com/acme/demo/wiki', 'https://github.com/acme/demo/commit/abc'], capture_output=True, text=True)
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertIn('[Thing](https://github.com/acme/demo/wiki/Thing#run)', (root / 'Home.md').read_text())
            self.assertIn('[Web](https://example.test/help)', (root / 'Home.md').read_text())
            self.assertIn('[Home](https://github.com/acme/demo/wiki/Home)', (root / 'classes/Example/Thing.md').read_text())
            self.assertIn('[Part](#run)', (root / 'classes/Example/Thing.md').read_text())
            self.assertIn('https://github.com/acme/demo/commit/abc', (root / 'Home.md').read_text())

    def test_excludes_agent_instruction_pages_from_generated_output(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'nested').mkdir()
            (root / 'Home.md').write_text('API docs\n')
            (root / 'nested/AGENTS.md').write_text('agent instructions\n')
            result = subprocess.run([sys.executable, str(SCRIPT), str(root), 'https://github.com/acme/demo/wiki', 'https://github.com/acme/demo/commit/abc'], capture_output=True, text=True)
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertFalse((root / 'nested/AGENTS.md').exists())

    def test_rejects_ambiguous_wiki_page_names_without_rewriting_content(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'a').mkdir()
            (root / 'b').mkdir()
            (root / 'Home.md').write_text('API docs\n')
            (root / 'a/Thing.md').write_text('first\n')
            (root / 'b/Thing.md').write_text('second\n')
            result = subprocess.run([sys.executable, str(SCRIPT), str(root), 'https://github.com/acme/demo/wiki', 'https://github.com/acme/demo/commit/abc'], capture_output=True, text=True)
            self.assertNotEqual(0, result.returncode)
            self.assertIn("Ambiguous Wiki page name", result.stderr)
            self.assertEqual('API docs\n', (root / 'Home.md').read_text())
            self.assertEqual('first\n', (root / 'a/Thing.md').read_text())

if __name__ == '__main__':
    unittest.main()
