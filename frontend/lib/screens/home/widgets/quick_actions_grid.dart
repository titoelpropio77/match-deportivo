import 'package:flutter/material.dart';

import 'quick_action_item.dart';

/// Grid of quick-action shortcuts shown on the dashboard.
class QuickActionsGrid extends StatelessWidget {
  const QuickActionsGrid({required this.actions, super.key});

  final List<QuickActionData> actions;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: 3,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 16,
      crossAxisSpacing: 8,
      children: actions.map((action) => QuickActionItem(data: action)).toList(),
    );
  }
}
