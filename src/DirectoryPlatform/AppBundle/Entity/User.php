<?php

namespace DirectoryPlatform\AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;


/**
 * @ORM\Entity(repositoryClass="DirectoryPlatform\AppBundle\Repository\UserRepository")
 * @ORM\Table(name="directory_platform_users")
 * @ORM\HasLifecycleCallbacks()
 */
class User extends BaseUser
{
	/**
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	protected $id;

	/**
	 * @ORM\OneToMany(targetEntity="Post", mappedBy="user")
	 */
	private $posts;

	/**
	 * @ORM\OneToMany(targetEntity="Listing", mappedBy="user")
	 */
	private $listings;

	/**
	 * @ORM\OneToMany(targetEntity="Review", mappedBy="user")
	 */
	private $reviews;

	/**
	 * @ORM\OneToMany(targetEntity="Favorite", mappedBy="user")
	 */
	private $favorites;

	/**
	 * @ORM\OneToOne(targetEntity="Profile", mappedBy="user")
	 **/
	private $profile;

	/**
	 * @ORM\Column(type="boolean", nullable=true, name="is_verified")
	 */
	private $isVerified;

	/**
	 * @ORM\Column(name="created", type="datetime")
	 */
	private $created;

	/**
	 * @ORM\Column(name="modified", type="datetime", nullable=true)
	 */
	private $modified;


    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $address;

    /**
     * @ORM\Column(type="float", precision=7, scale=7, nullable=true)
     */
    private $latitude;

    /**
     * @ORM\Column(type="float", precision=7, scale=7, nullable=true)
     */
    private $longitude;

	
	/**
	 * @ORM\Column(type="string", length=100, nullable=true)
	 */
	private $phone;

	/** 
	 * @ORM\Column(name="facebook_id", type="string", length=255, nullable=true)
	 */
    protected $facebook_id;

    /**
     * @ORM\Column(name="facebook_access_token", type="string", length=255, nullable=true) 
     */
    protected $facebook_access_token;

    /**
     * @ORM\Column(name="google_id", type="string", length=255, nullable=true) 
     */
    protected $google_id;

    /**
     * @ORM\Column(name="google_access_token", type="string", length=255, nullable=true) 
     */
    protected $google_access_token;

    /**
     * @var string
     *
     * @ORM\Column(name="stripeCustomerId", type="string", length=255, nullable=true)
     */
    private $stripeCustomerId;

	/**
	 * @ORM\PrePersist
	 */
	public function onPrePersist()
	{
		$this->created = new \DateTime('now');
		$this->modified = new \DateTime('now');
	}

	/**
	 * @ORM\PreUpdate
	 */
	public function onPreUpdate()
	{
		$this->modified = new \DateTime('now');
	}

	public function getUsername() {
		return $this->username;
	}

	public function getDisplayName() {
		if ($this->getProfile()) {
			if (!empty($this->getProfile()->getFirstName()) && !empty($this->getProfile()->getLastName())) {
				return $this->getProfile()->getFirstName() . ' ' . $this->getProfile()->getLastName();
			}
		}

		return $this->getUsername();
	}

	/**
	 * @return mixed
	 */
	public function getProfile()
	{
		return $this->profile;
	}

	/**
	 * @param mixed $profile
	 */
	public function setProfile($profile)
	{
		$this->profile = $profile;
	}

	public function getListings()
    {
	    return $this->listings;
    }

    public function setListings($listings)
    {
        $this->listings = $listings;
    }

	/**
	 * @return mixed
	 */
	public function getIsVerified()
	{
		return $this->isVerified;
	}

	/**
	 * @param mixed $isVerified
	 */
	public function setIsVerified($isVerified)
	{
		$this->isVerified = $isVerified;
	}



	/**
	 * @return mixed
	 */
	public function getPhone()
	{
		return $this->phone;
	}

	/**
	 * @param mixed $phone
	 */
	public function setPhone($phone)
	{
		$this->phone = $phone;
	}


    /**
     * Set created
     *
     * @param \DateTime $created
     *
     * @return User
     */
    public function setCreated($created)
    {
        $this->created = $created;
    
        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified
     *
     * @param \DateTime $modified
     *
     * @return User
     */
    public function setModified($modified)
    {
        $this->modified = $modified;
    
        return $this;
    }

    /**
     * Get modified
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

   

    /**
     * Add post
     *
     * @param \DirectoryPlatform\AppBundle\Entity\Post $post
     *
     * @return User
     */
    public function addPost(\DirectoryPlatform\AppBundle\Entity\Post $post)
    {
        $this->posts[] = $post;
    
        return $this;
    }

    /**
     * Remove post
     *
     * @param \DirectoryPlatform\AppBundle\Entity\Post $post
     */
    public function removePost(\DirectoryPlatform\AppBundle\Entity\Post $post)
    {
        $this->posts->removeElement($post);
    }

    /**
     * Get posts
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPosts()
    {
        return $this->posts;
    }

    /**
     * Add listing
     *
     * @param \DirectoryPlatform\AppBundle\Entity\Listing $listing
     *
     * @return User
     */
    public function addListing(\DirectoryPlatform\AppBundle\Entity\Listing $listing)
    {
        $this->listings[] = $listing;
    
        return $this;
    }

    /**
     * Remove listing
     *
     * @param \DirectoryPlatform\AppBundle\Entity\Listing $listing
     */
    public function removeListing(\DirectoryPlatform\AppBundle\Entity\Listing $listing)
    {
        $this->listings->removeElement($listing);
    }

    /**
     * Add review
     *
     * @param \DirectoryPlatform\AppBundle\Entity\Review $review
     *
     * @return User
     */
    public function addReview(\DirectoryPlatform\AppBundle\Entity\Review $review)
    {
        $this->reviews[] = $review;
    
        return $this;
    }

    /**
     * Remove review
     *
     * @param \DirectoryPlatform\AppBundle\Entity\Review $review
     */
    public function removeReview(\DirectoryPlatform\AppBundle\Entity\Review $review)
    {
        $this->reviews->removeElement($review);
    }

    /**
     * Get reviews
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getReviews()
    {
        return $this->reviews;
    }

    /**
     * Add favorite
     *
     * @param \DirectoryPlatform\AppBundle\Entity\Favorite $favorite
     *
     * @return User
     */
    public function addFavorite(\DirectoryPlatform\AppBundle\Entity\Favorite $favorite)
    {
        $this->favorites[] = $favorite;
    
        return $this;
    }

    /**
     * Remove favorite
     *
     * @param \DirectoryPlatform\AppBundle\Entity\Favorite $favorite
     */
    public function removeFavorite(\DirectoryPlatform\AppBundle\Entity\Favorite $favorite)
    {
        $this->favorites->removeElement($favorite);
    }

    /**
     * Get favorites
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFavorites()
    {
        return $this->favorites;
    }

    /**
     * Set address
     *
     * @param string $address
     *
     * @return User
     */
    public function setAddress($address)
    {
        $this->address = $address;
    
        return $this;
    }

    /**
     * Get address
     *
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set latitude
     *
     * @param float $latitude
     *
     * @return User
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;
    
        return $this;
    }

    /**
     * Get latitude
     *
     * @return float
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Set longitude
     *
     * @param float $longitude
     *
     * @return User
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;
    
        return $this;
    }

    /**
     * Get longitude
     *
     * @return float
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Set facebookId
     *
     * @param string $facebookId
     *
     * @return User
     */
    public function setFacebookId($facebookId)
    {
        $this->facebook_id = $facebookId;
    
        return $this;
    }

    /**
     * Get facebookId
     *
     * @return string
     */
    public function getFacebookId()
    {
        return $this->facebook_id;
    }

    /**
     * Set facebookAccessToken
     *
     * @param string $facebookAccessToken
     *
     * @return User
     */
    public function setFacebookAccessToken($facebookAccessToken)
    {
        $this->facebook_access_token = $facebookAccessToken;
    
        return $this;
    }

    /**
     * Get facebookAccessToken
     *
     * @return string
     */
    public function getFacebookAccessToken()
    {
        return $this->facebook_access_token;
    }

    /**
     * Set googleId
     *
     * @param string $googleId
     *
     * @return User
     */
    public function setGoogleId($googleId)
    {
        $this->google_id = $googleId;
    
        return $this;
    }

    /**
     * Get googleId
     *
     * @return string
     */
    public function getGoogleId()
    {
        return $this->google_id;
    }

    /**
     * Set googleAccessToken
     *
     * @param string $googleAccessToken
     *
     * @return User
     */
    public function setGoogleAccessToken($googleAccessToken)
    {
        $this->google_access_token = $googleAccessToken;
    
        return $this;
    }

    /**
     * Get googleAccessToken
     *
     * @return string
     */
    public function getGoogleAccessToken()
    {
        return $this->google_access_token;
    }

    /**
     * Set stripeCustomerId
     *
     * @param string $stripeCustomerId
     *
     * @return Client
     */
    public function setStripeCustomerId( $stripeCustomerId )
    {
        $this->stripeCustomerId = $stripeCustomerId;

        return $this;
    }

    /**
     * Get stripeCustomerId
     *
     * @return string
     */
    public function getStripeCustomerId()
    {
        return $this->stripeCustomerId;
    }
}
