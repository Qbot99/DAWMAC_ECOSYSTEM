//
//  ImageExtensions.swift
//  DawMac
//
//  Created by Hubert Kubot on 29/01/2026.
//

import UIKit
import ImageIO

extension UIImage {
    
    /// Funkcja normalizująca zdjęcie: naprawia obrót (EXIF) i zmniejsza do podanej maksymalnej szerokości/wysokości.
    func fixOrientationAndResize(maxDimension: CGFloat = 1920) -> UIImage {
        let currentSize = self.size
        let width = currentSize.width
        let height = currentSize.height
        
        var newSize = currentSize
        if width > maxDimension || height > maxDimension {
            let aspectRatio = width / height
            if width > height {
                newSize = CGSize(width: maxDimension, height: maxDimension / aspectRatio)
            } else {
                newSize = CGSize(width: maxDimension * aspectRatio, height: maxDimension)
            }
        }
        
        UIGraphicsBeginImageContextWithOptions(newSize, false, 1.0)
        self.draw(in: CGRect(origin: .zero, size: newSize))
        let normalizedImage = UIGraphicsGetImageFromCurrentImageContext()
        UIGraphicsEndImageContext()
        
        return normalizedImage ?? self
    }
    
    /// Szybkie zmniejszanie (Downsampling) obrazu bez obciążania pamięci RAM
    static func downsample(imageData: Data, to maxDimension: CGFloat) -> UIImage? {
        let imageSourceOptions = [kCGImageSourceShouldCache: false] as CFDictionary
        guard let imageSource = CGImageSourceCreateWithData(imageData as CFData, imageSourceOptions) else { return nil }
        
        let downsampleOptions = [
            kCGImageSourceCreateThumbnailFromImageAlways: true,
            kCGImageSourceShouldCacheImmediately: true,
            kCGImageSourceCreateThumbnailWithTransform: true,
            kCGImageSourceThumbnailMaxPixelSize: maxDimension
        ] as CFDictionary
        
        guard let downsampledImage = CGImageSourceCreateThumbnailAtIndex(imageSource, 0, downsampleOptions) else { return nil }
        return UIImage(cgImage: downsampledImage)
    }
}
