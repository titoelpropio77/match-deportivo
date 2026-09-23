/// Placeholder model for a highlighted court shown on the dashboard.
class FeaturedCourtModel {
  const FeaturedCourtModel({
    required this.name,
    required this.city,
    required this.rating,
    required this.pricePerHour,
    required this.imageUrl,
  });

  final String name;
  final String city;
  final double rating;
  final double pricePerHour;
  final String imageUrl;
}
