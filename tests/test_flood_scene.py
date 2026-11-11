import sys
from pathlib import Path
import unittest

import numpy as np

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from flood_scene import FLOOD_PROMPTS, NON_FLOOD_PROMPTS, scene_decision


class FloodSceneTests(unittest.TestCase):
    def scores(self, flood, non_flood):
        return [flood] * len(FLOOD_PROMPTS) + [non_flood] * len(NON_FLOOD_PROMPTS)

    def test_logo_or_indoor_scene_can_veto_a_high_cnn_estimate(self):
        result = scene_decision(self.scores(0.2, 0.3))
        self.assertEqual(result['status'], 'non_flood')

    def test_grey_flood_is_not_rejected_for_having_a_small_positive_margin(self):
        result = scene_decision(self.scores(0.3112, 0.3))
        self.assertEqual(result['status'], 'flood')

    def test_near_ties_are_inconclusive_instead_of_asserting_non_flood(self):
        result = scene_decision(self.scores(0.29, 0.3))
        self.assertEqual(result['status'], 'uncertain')

    def test_invalid_scene_scores_are_not_used_as_evidence(self):
        for scores in [[], self.scores(np.nan, 0.3)]:
            with self.subTest(scores=scores):
                with self.assertRaises(ValueError):
                    scene_decision(scores)


if __name__ == '__main__':
    unittest.main()
