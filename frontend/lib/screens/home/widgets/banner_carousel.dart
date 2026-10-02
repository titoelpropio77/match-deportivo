import 'dart:async';

import 'package:flutter/material.dart';

import '../../../models/banner_model.dart';

/// Home carousel: swipeable banners that advance on their own every [interval] (paused while the
/// user drags), with page dots. Each banner opens its link through [onBannerPressed].
class BannerCarousel extends StatefulWidget {
  const BannerCarousel({
    required this.banners,
    required this.onBannerPressed,
    this.interval = const Duration(seconds: 5),
    super.key,
  });

  static const height = 170.0;

  final List<BannerModel> banners;
  final ValueChanged<BannerModel> onBannerPressed;
  final Duration interval;

  @override
  State<BannerCarousel> createState() => _BannerCarouselState();
}

class _BannerCarouselState extends State<BannerCarousel> {
  final _controller = PageController();
  Timer? _timer;
  int _page = 0;

  @override
  void initState() {
    super.initState();
    _startTimer();
  }

  @override
  void didUpdateWidget(covariant BannerCarousel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.banners.length != widget.banners.length) {
      _page = 0;
      if (_controller.hasClients) _controller.jumpToPage(0);
      _startTimer();
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _startTimer() {
    _timer?.cancel();
    if (widget.banners.length < 2) return;
    _timer = Timer.periodic(widget.interval, (_) {
      if (!_controller.hasClients) return;
      final next = (_page + 1) % widget.banners.length;
      _controller.animateToPage(
        next,
        duration: const Duration(milliseconds: 450),
        curve: Curves.easeInOut,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final banners = widget.banners;
    if (banners.isEmpty) return const SizedBox.shrink();

    return Column(
      children: [
        SizedBox(
          height: BannerCarousel.height,
          child: NotificationListener<ScrollNotification>(
            // Restart the countdown after a manual swipe so it does not jump right away.
            onNotification: (notification) {
              if (notification is ScrollStartNotification &&
                  notification.dragDetails != null) {
                _timer?.cancel();
              } else if (notification is ScrollEndNotification) {
                _startTimer();
              }
              return false;
            },
            child: PageView.builder(
              controller: _controller,
              itemCount: banners.length,
              onPageChanged: (page) => setState(() => _page = page),
              itemBuilder: (context, index) => Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2),
                child: _BannerSlide(
                  banner: banners[index],
                  onPressed: () => widget.onBannerPressed(banners[index]),
                ),
              ),
            ),
          ),
        ),
        if (banners.length > 1) ...[
          const SizedBox(height: 8),
          _PageDots(count: banners.length, current: _page),
        ],
      ],
    );
  }
}

class _BannerSlide extends StatelessWidget {
  const _BannerSlide({required this.banner, required this.onPressed});

  final BannerModel banner;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final actionable = banner.link.isActionable;
    final background = ColoredBox(color: banner.backgroundColor);

    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: Material(
        color: banner.backgroundColor,
        child: InkWell(
          onTap: actionable ? onPressed : null,
          child: Stack(
            fit: StackFit.expand,
            children: [
              if (banner.imageUrl != null && banner.imageUrl!.isNotEmpty)
                Image.network(
                  banner.imageUrl!,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => background,
                  loadingBuilder: (context, child, progress) =>
                      progress == null ? child : background,
                ),
              DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Colors.black.withValues(
                        alpha: banner.imageUrl == null ? 0.05 : 0.55,
                      ),
                      Colors.black.withValues(
                        alpha: banner.imageUrl == null ? 0.25 : 0.15,
                      ),
                    ],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    Text(
                      banner.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    if (banner.subtitle != null &&
                        banner.subtitle!.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        banner.subtitle!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: Colors.white.withValues(alpha: 0.92),
                        ),
                      ),
                    ],
                    if (actionable &&
                        banner.buttonLabel != null &&
                        banner.buttonLabel!.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      FilledButton(
                        onPressed: onPressed,
                        style: FilledButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: Colors.black87,
                          visualDensity: VisualDensity.compact,
                        ),
                        child: Text(banner.buttonLabel!),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PageDots extends StatelessWidget {
  const _PageDots({required this.count, required this.current});

  final int count;
  final int current;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        for (var index = 0; index < count; index++)
          AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            margin: const EdgeInsets.symmetric(horizontal: 3),
            width: index == current ? 18 : 7,
            height: 7,
            decoration: BoxDecoration(
              color: index == current ? colors.primary : colors.outlineVariant,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
      ],
    );
  }
}
