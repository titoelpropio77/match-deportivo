import '../models/featured_court_model.dart';

/// Static sample data used until the courts catalog API is available.
const dummyFeaturedCourts = <FeaturedCourtModel>[
  FeaturedCourtModel(
    name: 'Complejo Wally Sur',
    city: 'Santa Cruz',
    rating: 4.8,
    pricePerHour: 120,
    imageUrl: 'https://picsum.photos/seed/wally-sur/400/300',
  ),
  FeaturedCourtModel(
    name: 'Canchas El Torneo',
    city: 'Equipetrol',
    rating: 4.5,
    pricePerHour: 110,
    imageUrl: 'https://picsum.photos/seed/el-torneo/400/300',
  ),
  FeaturedCourtModel(
    name: 'Wally Center Equipetrol',
    city: 'Equipetrol',
    rating: 4.6,
    pricePerHour: 130,
    imageUrl: 'https://picsum.photos/seed/wally-center/400/300',
  ),
  FeaturedCourtModel(
    name: 'Arena Norte',
    city: 'Zona Norte',
    rating: 4.3,
    pricePerHour: 100,
    imageUrl: 'https://picsum.photos/seed/arena-norte/400/300',
  ),
];
