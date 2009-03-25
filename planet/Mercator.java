
/*
Java Implementation by Moshe Sayag, based on the JavaScript code published
above, 17:11, 15.1.2008

taken from http://wiki.openstreetmap.org/index.php/Mercator
*/

public class Mercator {
    final private static double R_MAJOR = 6378137.0;
    final private static double R_MINOR = 6356752.3142;

    public double[] merc(double x, double y) {
        return new double[] {mercX(x), mercY(y)};
    }

    public double  mercX(double lon) {
        return R_MAJOR * Math.toRadians(lon);
    }

    public double mercY(double lat) {
        if (lat > 89.5) {
            lat = 89.5;
        }
        if (lat < -89.5) {
            lat = -89.5;
        }
        double temp = R_MINOR / R_MAJOR;
        double es = 1.0 - (temp * temp);
        double eccent = Math.sqrt(es);
        double phi = Math.toRadians(lat);
        double sinphi = Math.sin(phi);
        double con = eccent * sinphi;
        double com = 0.5 * eccent;
        con = Math.pow(((1.0-con)/(1.0+con)), com);
        double ts = Math.tan(0.5 * ((Math.PI*0.5) - phi))/con;
        double y = 0 - R_MAJOR * Math.log(ts);
        return y;
    }
}

